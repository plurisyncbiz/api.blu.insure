<?php
use DI\ContainerBuilder;
use Slim\App;
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

// -----------------------------------------------------------------------------
// 1. LOAD ENVIRONMENT VARIABLES FIRST
// -----------------------------------------------------------------------------
// This MUST happen before you instantiate the Container or require settings
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Build DI container instance
$container = (new ContainerBuilder())
    ->addDefinitions(__DIR__ . '/container.php')
    ->build();
// Create App instance
return $container->get(App::class);
