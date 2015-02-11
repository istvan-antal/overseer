<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Overseer\TimeExtension;
use Overseer\TimeHelper;
use Overseer\DateTimeFormatter;

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
    ;
    return $oauth;
});

$app->get('/', function() use($app) {
    $oauth = $app['session']->get('oauth');

    $avgSupportTicketTimeSpent = 0;
    $supportTickets = null;

    if (!empty($oauth)) {
        $tickets = $app['oauth']->getClient(
            $oauth['oauth_token'], $oauth['oauth_token_secret']
        )->get('rest/api/2/search?jql=project%20%3D%20AL%20AND%20issuetype%20%3D%20"Support%20Request"%20ORDER%20BY%20created%20DESC')->
                send()->json();
        
        $totalSupportTicketTime = 0;
        
        foreach ($tickets['issues'] as $issue) {
            if ($issue['fields']['timespent'] === null) {
                $totalSupportTicketTime += strtotime($issue['fields']['resolutiondate']) - strtotime($issue['fields']['created']);
            } else {
                $totalSupportTicketTime += $issue['fields']['timespent'];
            }
        }
        
        $avgSupportTicketTimeSpent = $totalSupportTicketTime / count($tickets['issues']);
        
        $supportTickets = array_map(function ($item) { 
            return array(
                'id' => $item['key'],
                'summary' => $item['fields']['summary'],
                'created' => new DateTime($item['fields']['created'])
            );
        }, $tickets['issues']);
    }
    
    $avgSupportTicketTimeSpentTs = new DateTime();
    $avgSupportTicketTimeSpentTs->setTimestamp(time() + $avgSupportTicketTimeSpent);

    return $app['twig']->render('layout.twig', array(
        'oauth' => $oauth,
        'avgSupportTicketTimeSpent' => $avgSupportTicketTimeSpent,
        'avgSupportTicketTimeSpentTs' => $avgSupportTicketTimeSpentTs,
        'supportTickets' => $supportTickets
    ));
})->bind('home');

$app->get('/connect', function() use($app) {
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

    return $app->redirect(
                    $app['url_generator']->generate('home')
    );
})->bind('callback');

$app->get('/reset', function() use($app) {
    $app['session']->set('oauth', null);

    return $app->redirect(
                    $app['url_generator']->generate('home')
    );
})->bind('reset');

$app->run();
