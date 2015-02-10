<?php
require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;
$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__ . '/../views',
));
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

$app['session.storage.handler'] = null;

$app['oauth'] = $app->share(function() use($app){
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

$app->get('/', function() use($app){
	$oauth = $app['session']->get('oauth');

	if (empty($oauth)) {
		$priorities = null;
	} else {
		$priorities = $app['oauth']->getClient(
			$oauth['oauth_token'], 
			$oauth['oauth_token_secret']
		)->get('rest/api/2/priority')->send()->json();
                
                $tickets = $app['oauth']->getClient(
			$oauth['oauth_token'], 
			$oauth['oauth_token_secret']
		)->get('rest/api/2/search?jql=project%20%3D%20AL%20AND%20issuetype%20%3D%20"Support%20Request"')->send()->json();
	}

	return $app['twig']->render('layout.twig', array(
		'oauth' => $oauth,
                'tickets' => $tickets,
		'priorities' => $priorities,
	));
})->bind('home');

$app->get('/connect', function() use($app){
	$token = $app['oauth']->requestTempCredentials();
	
	$app['session']->set('oauth', $token);

	return $app->redirect(
		$app['oauth']->makeAuthUrl()
	);
})->bind('connect');

$app->get('/callback', function() use($app){
	$verifier = $app['request']->get('oauth_verifier');

	if (empty($verifier)) {
		throw new \InvalidArgumentException("There was no oauth verifier in the request");
	}
	
	$tempToken = $app['session']->get('oauth');

	$token = $app['oauth']->requestAuthCredentials(
		$tempToken['oauth_token'],
		$tempToken['oauth_token_secret'],
		$verifier
	);

	$app['session']->set('oauth', $token);

    return $app->redirect(
		$app['url_generator']->generate('home')
	);
})->bind('callback');

$app->get('/reset', function() use($app){
	$app['session']->set('oauth', null);

    return $app->redirect(
		$app['url_generator']->generate('home')
	);
})->bind('reset');

$app->run();