<?php

require_once __DIR__ . '/../bootstrap.php';

require '../config.php';

use Overseer\TimeExtension;
use Overseer\TimeHelper;
use Overseer\JIRA;

use Entity\Widget;

use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();

/* @var $entityManager \Doctrine\ORM\EntityManager */

$app['doctrine'] = $entityManager;

$app['debug'] = true;
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../views',
));
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app['twig']->addExtension(new TimeExtension(new TimeHelper()));

$app['session.storage.handler'] = null;

$app['oauth'] = $app->share(function() use ($app, $config) {
    $requestOptions = null;
    if (isset($config['jira']['requestOptions'])) {
        $requestOptions = $config['jira']['requestOptions'];
    }

    $oauth = new Overseer\OAuthWrapper($config['jira']['baseUrl'], $requestOptions);
    $oauth->setPrivateKey('../overseer.pem')
            ->setConsumerKey('1234567890')
            ->setConsumerSecret('abcd1234567890')
            ->setRequestTokenUrl('plugins/servlet/oauth/request-token')
            ->setAuthorizationUrl('plugins/servlet/oauth/authorize?oauth_token=%s')
            ->setAccessTokenUrl('plugins/servlet/oauth/access-token')
            ->setCallbackUrl(
                    $app['url_generator']->generate('callback', array(), true)
    );

    return $oauth;
});

$app->error(function (\Guzzle\Http\Exception\ClientErrorResponseException $e, $code) use ($app) {
    // Unauthorized
    if ($e->getResponse()->getStatusCode() === 401) {
        $app['session']->clear();
        return $app->redirect('/connect');
    }
});

$app->before(function (Request $request) use ($app) {
    if ($request->getRequestUri() === '/connect') {
        return;
    }

    $oauthConfig = $app['session']->get('oauth');

    if (empty($oauthConfig)) {
        return $app->redirect('/connect');
    }
});

$app['jira'] = $app->share(function() use ($app, $config) {
    $oauthConfig = $app['session']->get('oauth');

    if (empty($oauthConfig)) {
        throw new Exception("Invalid session");
    }

    $jira = new JIRA($config['jira']['baseUrl'], $app['oauth'], $oauthConfig);
    return $jira;
});


$app->get('/status', function () use ($app) {
    $oauthConfig = $app['session']->get('oauth');

    if (empty($oauthConfig)) {
        return $app->redirect('/connect');
    }

    $jira = new JIRA($app['oauth'], $oauthConfig);

    $sprintIssues = $jira->getIssuesForSprint();

    $sprintIssuesSolvedCount = count(array_filter($sprintIssues, function ($issue) {
        return in_array($issue['status'], array('Resolved', 'Closed'));
    }));

    $sprintIssuesCount = count($sprintIssues);

    $cards = array();

    $cards []= array(
        'title' => 'New support tickets',
        'issues' => $jira->getIncomingSupportTickets(),
        'options' => array()
    );
    $cards []= array(
        'title' => 'Ready for review',
        'issues' => $jira->getTestingIssues(),
        'options' => array()
    );
    $cards []= array(
        'title' => 'Issues in progress',
        'issues' => $jira->getIssuesWorkedOn(),
        'options' => array(
            'includeAssignee' => true
        )
    );

    $cards []= array(
        'title' => 'Issues resolved today',
        'issues' => $jira->getIssuesResolvedToday(),
        'options' => array(
            'includeAssignee' => true
        )
    );

    $cards []= array(
        'title' => 'Issues resolved yesterday',
        'issues' => $jira->getIssuesResolvedYesterday(),
        'options' => array(
            'includeAssignee' => true
        )
    );

    $versions = $jira->getVersions();

    $unreleasedVersions = array_filter($versions, function ($version) {
        return !$version['released'];
    });

    foreach ($unreleasedVersions as &$version) {
        $version['issues'] = $jira->getIssuesFixedForVersion($version['name']);
    }

    foreach ($unreleasedVersions as $version) {
        $cards []= array(
            'title' => 'Release: '.$version['name'],
            'issues' =>$version['issues'],
            'options' => array()
        );
    }

    return $app['twig']->render('status.twig', array(
        'menu' => 'home',
        'oauth' => $oauthConfig,
        'cards' => array_filter($cards, function ($card) { return count($card['issues']); }),
        'sprintIssuesSolvedCount' => $sprintIssuesSolvedCount,
        'sprintIssuesCount' => $sprintIssuesCount,
    ));
})->bind('status');

$app->get('/', function () use ($app) {
    $jira = $app['jira']; /* @var $jira JIRA */
    $entityManager = $app['doctrine'];
    $widgetRepository = $entityManager->getRepository('Entity\Widget');

    $cards = array();
    
    foreach ($widgetRepository->findAll() as $widget) {
        /* @var $widget Widget */
        $type = $widget->getType();
        
        $card = array(
            'title' => $widget->getName(),
            'issues' => array(),
            'options' => $widget->getDisplayOptions()
        );
        
        if ($type === 'releases') {
            $versions = $jira->getVersions($widget->getQueryOptions()['project']);

            $unreleasedVersions = array_filter($versions, function ($version) {
                return !$version['released'];
            });
            
            usort($unreleasedVersions, function ($av, $bv) {
                $a = 0;
                $b = 0;
                
                if (isset($av['releaseDate'])) {
                    $a = strtotime($av['releaseDate']);
                }
                
                if (isset($bv['releaseDate'])) {
                    $b = strtotime($bv['releaseDate']);
                }
                
                $result = $b - $a;
                
                if (!$result) {
                    $result = strcmp($bv['name'], $av['name']);
                }
                
                return $result;
            });

            foreach ($unreleasedVersions as &$version) {
                $title = $version['name'];
                
                if ($version['description']) {
                    $title = $version['description'].' - '.$title;
                }
                
                if (isset($version['releaseDate'])) {
                    $title .= ' - '.$version['releaseDate'];
                }
                
                $issues = $jira->getIssuesFixedForVersion($widget->getQueryOptions()['project'], $version['name']);
                
                $cards []= array(
                    'title' => $title,
                    'issues' => $issues,
                    'options' => $widget->getDisplayOptions(),
                    'resolvedIssueCount' => array_reduce($issues, function ($a, $b) {
                        if ($b['status'] === 'Closed') {
                            return $a + 1;
                        }
                        if (!is_null($b['resolved'])) {
                            return $a + 1;
                        }
                        return $a;
                    }, 0)
                );
            }
            continue;
        }
        
        switch ($type) {
            case 'custom':
                $card['issues'] = $jira->getIssues($widget->getQueryOptions());
                break;
            case 'myTodo':
                $card['issues'] = $jira->getTodoList();
                break;
            default:
                $method = 'get'.ucfirst($type);
                $queryOptions = $widget->getQueryOptions();
                if (isset($queryOptions['project'])) {
                    $card['issues'] = $jira->$method($queryOptions['project']);
                } else {
                    $card['issues'] = $jira->$method();
                } 
        }
        
        $cards []= $card;
    }

    return $app['twig']->render('home.twig', array(
        'menu' => 'home',
        'cards' => array_filter($cards, function ($card) { return count($card['issues']); })
    ));
})->bind('home');

$app->get('/widget/new', function () use ($app) {
    $jira = $app['jira'];
    return $app['twig']->render('widgetForm.twig', array(
        'menu' => 'home',
        'projects' => $jira->getProjects(),
        'statuses' => $jira->getAllStatuses()
    ));
})->bind('new_wiget');

$app->post('/widget/create', function (Request $request) use ($app) {
    $post = $request->request->all();
    $entityManager = $app['doctrine'];
    
    $widget = new Widget();
    $widget->setName($post['name']);
    $widget->setType($post['type']);
    
    $queryOptions = $widget->getQueryOptions();
    foreach ($post['queryOptions'] as $k => $v) {
        if (is_array($v) && empty($v)) {
            continue;
        }
        
        if (is_string($v) && !$v) {
            continue;
        }
        
        $queryOptions[$k] = $v;
    }
    $widget->setQueryOptions($queryOptions);
    
    $displayOptions = $widget->getDisplayOptions();
    foreach ($post['displayOptions'] as $k => $v) {
        if (is_array($v) && empty($v)) {
            continue;
        }
        
        if (is_string($v) && !$v) {
            continue;
        }
        
        $displayOptions[$k] = $v;
    }
    $widget->setDisplayOptions($displayOptions);
    
    $entityManager->persist($widget);
    $entityManager->flush();
    
    return $app->redirect('/');
})->bind('create_wiget');

$app->get('/{project}/home', function ($project) use ($app) {
    $jira = $app['jira'];
    $jira->setProject($project);

    $cards = array();
    
    $cards []= array(
        'title' => 'Issues in progress',
        'issues' => $jira->getIssuesWorkedOn($project),
        'options' => array(
            'includeAssignee' => true
        )
    );
    
    $cards []= array(
        'title' => 'Resolved today',
        'issues' => $jira->getIssuesResolvedToday($project),
        'options' => array(
            'includeAssignee' => true
        )
    );
    
    $cards []= array(
        'title' => 'Resolved yesterday',
        'issues' => $jira->getIssuesResolvedYesterday($project),
        'options' => array(
            'includeAssignee' => true
        )
    );
    
    $cards []= array(
        'title' => 'Unassigned issues',
        'issues' => $jira->getUnassignedIssues($project),
        'options' => array(
        )
    );
    
    $versions = $jira->getVersions($project);

    $unreleasedVersions = array_filter($versions, function ($version) {
        return !$version['released'];
    });
    
    foreach ($unreleasedVersions as &$version) {
        $cards []= array(
            'title' => $version['name'],
            'issues' => $jira->getIssuesFixedForVersion($project, $version['name']),
            'options' => array()
        );
    }

    return $app['twig']->render('project.twig', array(
        'menu' => 'home',
        'cards' => array_filter($cards, function ($card) { return count($card['issues']); }),
    ));
})->bind('project_home');

$app->get('/release', function () use ($app) {
    $oauthConfig = $app['session']->get('oauth');

    if (empty($oauthConfig)) {
        return $app->redirect('/connect');
    }

    $jira = new JIRA($app['oauth'], $oauthConfig);

    $versions = $jira->getVersions();

    $unreleasedVersions = array_filter($versions, function ($version) {
        return !$version['released'];
    });

    foreach ($unreleasedVersions as &$version) {
        $version['issues'] = $jira->getIssuesFixedForVersion($version['name']);
    }

    $issuesWithoutFixVersion = $jira->getIssuesWithoutFixedVersionForSprint();

    $cards = array();

    $components = array();

    foreach ($issuesWithoutFixVersion as $issue) {
        if (empty($issue['components'])) {
            $component = '*None*';
        } else {
            $component = $issue['components'][0]['name'];
        }

        if (!isset($components[$component])) {
            $components[$component] = array();
        }
        $components[$component][]=$issue;
    }

    foreach ($components as $component => $issues) {
        $cards []= array(
            'title' => $component,
            'issues' => $issues,
            'options' => array()
        );
    }

    return $app['twig']->render('release.twig', array(
        'menu' => 'home',
        'cards' => $cards,
        'unreleasedVersions' => $unreleasedVersions,
        'issuesWithoutFixVersion' => $issuesWithoutFixVersion
    ));
})->bind('release');

$app->get('/releases', function () use ($app) {
    $oauthConfig = $app['session']->get('oauth');

    if (empty($oauthConfig)) {
        return $app->redirect('/connect');
    }

    $jira = new JIRA($app['oauth'], $oauthConfig);

    $versions = $jira->getVersions();

    $unreleasedVersions = array_filter($versions, function ($version) {
        return !$version['released'];
    });

    $cards = array();

    foreach ($versions as &$version) {
        $cards []= array(
            'title' => $version['name'],
            'issues' => $jira->getIssuesFixedForVersion($version['name']),
            'options' => array()
        );
    }

    $cards = array_reverse($cards);

    return $app['twig']->render('releases.twig', array(
        'menu' => 'home',
        'cards' => $cards
    ));
})->bind('releases');

$app->get('/testing', function () use ($app) {
    $oauthConfig = $app['session']->get('oauth');

    if (empty($oauthConfig)) {
        return $app->redirect('/connect');
    }

    $jira = new JIRA($app['oauth'], $oauthConfig);

    $cards = array();

    $cards []= array(
        'title' => 'Ready for review',
        'issues' => $jira->getTestingIssues(),
    );

    return $app['twig']->render('testing.twig', array(
        'menu' => 'testing',
        'cards' => $cards
    ));
})->bind('testing');

$app->get('/support', function () use ($app) {
    $oauthConfig = $app['session']->get('oauth');

    if (empty($oauthConfig)) {
        return $app->redirect('/connect');
    }

    $jira = new JIRA($app['oauth'], $oauthConfig);

    $cards = array();

    $cards []= array(
        'title' => 'Support ticket',
        'issues' => $jira->getSupportStats()['issues'],
    );

    return $app['twig']->render('testing.twig', array(
        'menu' => 'support',
        'cards' => $cards
    ));
})->bind('support');

$app->get('/components', function () use ($app) {
    $oauthConfig = $app['session']->get('oauth');

    if (empty($oauthConfig)) {
        return $app->redirect('/connect');
    }

    $jira = new JIRA($app['oauth'], $oauthConfig);

    $cards = array();

    $components = array();

    foreach ($jira->getIssuesForSprint() as $issue) {
        if (empty($issue['components'])) {
            $component = '*None*';
        } else {
            $component = $issue['components'][0]['name'];
        }

        if (!isset($components[$component])) {
            $components[$component] = array();
        }
        $components[$component][]=$issue;
    }

    foreach ($components as $component => $issues) {
        $cards []= array(
            'title' => $component,
            'issues' => $issues,
        );
    }

    return $app['twig']->render('testing.twig', array(
        'menu' => 'components',
        'cards' => $cards
    ));
})->bind('components');

$app->get('/team', function () use ($app) {
    $oauthConfig = $app['session']->get('oauth');

    if (empty($oauthConfig)) {
        return $app->redirect('/connect');
    }

    $jira = new JIRA($app['oauth'], $oauthConfig);

    $sprintIssues = $jira->getIssuesForSprint();

    $sprintIssuesSolvedCount = count(array_filter($sprintIssues, function ($issue) {
        return in_array($issue['status'], array('Resolved', 'Closed'));
    }));

    $sprintIssuesCount = count($sprintIssues);

    $cards = array();

    /*$cards []= array(
        'title' => 'New support tickets',
        'issues' => $jira->getIncomingSupportTickets(),
    );
    $cards []= array(
        'title' => 'Ready for review',
        'issues' => $jira->getTestingIssuesForSprint(),
    );
    */

    $supportStats = $jira->getSupportStats();

    $avgSupportTicketTimeSpentTs = new DateTime();
    $avgSupportTicketTimeSpentTs->setTimestamp(time() + $supportStats['avgResolutionTime']);

    $assignees = array();

    foreach ($sprintIssues as $issue) {
        if (isset($issue['assignee']['name']) && !in_array($issue['assignee']['name'], $assignees)) {
            $assignees[]=$issue['assignee']['name'];
        }
    }

    $issuesByAssignee = array_map(function ($assignee) use ($sprintIssues) {
        return array(
            'assignee' => $assignee,
            'issues' => array_filter($sprintIssues, function ($issue) use ($assignee) {
                return isset($issue['assignee']['name']) && $issue['assignee']['name'] === $assignee;
             })
        );
    }, $assignees);


    $issuesUnassigned = array_filter($sprintIssues, function ($issue) {
        return !isset($issue['assignee']['name']);
    });

    if (count($issuesUnassigned)) {
        $issuesByAssignee[]= array(
            'assignee' => 'unassigned',
            'issues' => $issuesUnassigned
        );
    }

    return $app['twig']->render('team.twig', array(
        'menu' => 'team',
        'oauth' => $oauthConfig,
        'cards' => array_filter($cards, function ($card) { return count($card['issues']); }),
        'sprintIssuesSolvedCount' => $sprintIssuesSolvedCount,
        'sprintIssuesCount' => $sprintIssuesCount,
        'avgSupportTicketTimeSpentTs' => $avgSupportTicketTimeSpentTs,
        'issuesByAssignee' => $issuesByAssignee
    ));
})->bind('team');

$app->get('/report', function() use ($app) {
    $oauthConfig = $app['session']->get('oauth');

    if (empty($oauthConfig)) {
        $app['session']->set('redirectTo', '/report');
        return $app->redirect('/connect');
    }

    return $app['twig']->render('create.twig', array(
        'menu' => 'report',
        'oauth' => $oauthConfig
    ));
})->bind('create');

$app->post('/create', function (Request $request) use ($app) {
    $jira = new JIRA($app['oauth'], $app['session']->get('oauth'));
    $jira->createIssue(array(
        'type' => 'Support Request',
        'priority' => ($request->get('is_blocker') ? 'P1' : 'P2'),
        'summary' => $request->get('summary'),
        'description' => $request->get('description')
    ));
    return $app->redirect('/connect');
})->bind('do_create');

$app->get('/connect', function () use ($app) {
    $token = $app['oauth']->requestTempCredentials();

    $app['session']->set('oauth', $token);

    return $app->redirect(
                    $app['oauth']->makeAuthUrl()
    );
})->bind('connect');

$app->get('/callback', function() use($app) {
    $verifier = $app['request']->get('oauth_verifier');

    if (empty($verifier)) {
        throw new \InvalidArgumentException("There was no oauth verifier in the request");
    }

    $tempToken = $app['session']->get('oauth');

    $token = $app['oauth']->requestAuthCredentials(
            $tempToken['oauth_token'], $tempToken['oauth_token_secret'], $verifier
    );

    $app['session']->set('oauth', $token);

    $redirectUrl = $app['session']->get('redirectTo');

    if ($redirectUrl) {
        $app['session']->set('redirectTo', null);
        return $app->redirect($redirectUrl);
    }

    return $app->redirect($app['url_generator']->generate('home'));
})->bind('callback');

$app->get('/reset', function() use($app) {
    $app['session']->set('oauth', null);

    return $app->redirect(
                    $app['url_generator']->generate('home')
    );
})->bind('reset');

$app->run();
