<?php
require_once __DIR__.'/vendor/autoload.php';

use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;

// Create a simple "default" Doctrine ORM configuration for Annotations
$isDevMode = true;
$config = Setup::createAnnotationMetadataConfiguration(array(__DIR__ . "/src"), $isDevMode);

// obtaining the entity manager
$entityManager = EntityManager::create(array(
        'driver' => 'pdo_sqlite',
        'path' => __DIR__.'/storage/db.sqlite'
    ),
    $config
);
/* @var $entityManager EntityManager */