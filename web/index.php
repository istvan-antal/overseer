<?php

require_once __DIR__ . '/../vendor/autoload.php';

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

$app['oauth'] = $app->share(function() use($app) {
    $oauth = new Atlassian\OAuthWrapper('https://jira.condenastint.com/');
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

$app->get('/', function () use ($app) {
    $oauthConfig = $app['session']->get('oauth');
    
    if (empty($oauthConfig)) {
        return $app->redirect('/connect');
    }
    
    $jira = new JIRA($app['oauth'], $oauthConfig);
    
    $mySprintIssues = $jira->getMyIssuesForSprint();
    
    $mySprintIssuesSolvedCount = count(array_filter($mySprintIssues, function ($issue) {
        return in_array($issue['status'], array('Resolved', 'Closed'));
    }));
    
    $mySprintIssuesCount = count($mySprintIssues);
    
    $cards = array();
    
    $cards []= array(
        'title' => 'New support tickets',
        'issues' => $jira->getIncomingSupportTickets(),
    );
    $cards []= array(
        'title' => 'My Todo',
        'issues' => $jira->getTodoList(),
    );
    $cards []= array(
        'title' => 'Resolved today',
        'issues' => $jira->getIssuesResolvedToday(),
    );
    $cards []= array(
        'title' => 'Resolved yesterday',
        'issues' => $jira->getIssuesResolvedYesterday(),
    );

    return $app['twig']->render('home.twig', array(
        'oauth' => $oauthConfig,
        'cards' => array_filter($cards, function ($card) { return count($card['issues']); }),
        'mySprintIssuesSolvedCount' => $mySprintIssuesSolvedCount,
        'mySprintIssuesCount' => $mySprintIssuesCount
    ));
})->bind('home');

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
    
    $cards []= array(
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
    );
    
    $supportStats = $jira->getSupportStats();
    
    $avgSupportTicketTimeSpentTs = new DateTime();
    $avgSupportTicketTimeSpentTs->setTimestamp(time() + $supportStats['avgResolutionTime']);

    return $app['twig']->render('team.twig', array(
        'oauth' => $oauthConfig,
        'cards' => array_filter($cards, function ($card) { return count($card['issues']); }),
        'sprintIssuesSolvedCount' => $sprintIssuesSolvedCount,
        'sprintIssuesCount' => $sprintIssuesCount,
        'avgSupportTicketTimeSpentTs' => $avgSupportTicketTimeSpentTs
    ));
})->bind('team');

$app->get('/report', function() use ($app) {
    $oauthConfig = $app['session']->get('oauth');
    
    if (empty($oauthConfig)) {
        $app['session']->set('redirectTo', '/report');
        return $app->redirect('/connect');
    }
    
    return $app['twig']->render('create.twig', array(
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
