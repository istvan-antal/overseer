<?php

require_once __DIR__ . '/../vendor/autoload.php';

require '../config.php';

use Overseer\TimeExtension;
use Overseer\TimeHelper;
use Overseer\JIRA;

use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();
$app['debug'] = true;
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../views',
));
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app['twig']->addExtension(new TimeExtension(new TimeHelper()));

$app['session.storage.handler'] = null;

$app['oauth'] = $app->share(function() use($app, $config) {
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
    $oauthConfig = $app['session']->get('oauth');

    if (empty($oauthConfig)) {
        return $app->redirect('/connect');
    }

    $jira = new JIRA($app['oauth'], $oauthConfig);

    $projects = $jira->getProjects();

    $cards = array();

    $cards []= array(
        'title' => 'My Todo',
        'issues' => $jira->getTodoList(),
        'options' => array()
    );

    return $app['twig']->render('projects.twig', array(
        'menu' => 'home',
        'oauth' => $oauthConfig,
        'projects' => $projects,
        'cards' => array_filter($cards, function ($card) { return count($card['issues']); })
    ));

    /*$mySprintIssues = $jira->getMyIssuesForSprint();

    $mySprintIssuesSolvedCount = count(array_filter($mySprintIssues, function ($issue) {
        return in_array($issue['status'], array('Resolved', 'Closed'));
    }));

    $mySprintIssuesCount = count($mySprintIssues);

    $cards = array();

    $cards []= array(
        'title' => 'New support tickets',
        'issues' => $jira->getIncomingSupportTickets(),
        'options' => array()
    );

    $cards []= array(
        'title' => 'To be reviewed',
        'issues' => $jira->getMyTestingIssuesForSprint(),
        'options' => array()
    );


    $cards []= array(
        'title' => 'Resolved today by me',
        'issues' => $jira->getMyIssuesResolvedToday(),
        'options' => array()
    );
    $cards []= array(
        'title' => 'Resolved yesterday by me',
        'issues' => $jira->getMyIssuesResolvedYesterday(),
        'options' => array()
    );
    $cards []= array(
        'title' => 'Issues in progress',
        'issues' => $jira->getIssuesWorkedOn(),
        'options' => array(
            'includeAssignee' => true
        )
    );

    */
})->bind('home');

$app->get('/{project}/home', function ($project) use ($app) {
    $oauthConfig = $app['session']->get('oauth');

    if (empty($oauthConfig)) {
        return $app->redirect('/connect');
    }

    $jira = new JIRA($app['oauth'], $oauthConfig);
    $jira->setProject($project);

    $cards = array();

    $cards []= array(
        'title' => 'My Todo',
        'issues' => $jira->getTodoList(),
        'options' => array()
    );

    return $app['twig']->render('home.twig', array(
        'menu' => 'home',
        'oauth' => $oauthConfig,
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
    $cards []= array(
        'title' => 'Resolved today',
        'issues' => $jira->getIssuesResolvedToday(),
    );
    $cards []= array(
        'title' => 'Resolved yesterday',
        'issues' => $jira->getIssuesResolvedYesterday(),
    );*/

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
