<?php
require_once './bootstrap.php';

$commands = array(
    // Migrations Commands
    new \Doctrine\DBAL\Migrations\Tools\Console\Command\DiffCommand(),
    new \Doctrine\DBAL\Migrations\Tools\Console\Command\ExecuteCommand(),
    new \Doctrine\DBAL\Migrations\Tools\Console\Command\GenerateCommand(),
    new \Doctrine\DBAL\Migrations\Tools\Console\Command\MigrateCommand(),
    new \Doctrine\DBAL\Migrations\Tools\Console\Command\StatusCommand(),
    new \Doctrine\DBAL\Migrations\Tools\Console\Command\VersionCommand()
);

/* @var $entityManager EntityManager */

$set = \Doctrine\ORM\Tools\Console\ConsoleRunner::createHelperSet($entityManager);

$set->set(new \Symfony\Component\Console\Helper\DialogHelper(), 'dialog');

return $set;