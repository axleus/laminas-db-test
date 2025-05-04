<?php

declare(strict_types=1);

use Axleus\DevTools\StopWatch;
use Tracy\Debugger;

// Delegate static file requests back to the PHP built-in webserver
if (PHP_SAPI === 'cli-server' && $_SERVER['SCRIPT_FILENAME'] !== __FILE__) {
    return false;
}

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

// Enable Tracy Debugger

Debugger::enable();

/**
 * Self-called anonymous function that creates its own scope and keeps the global namespace clean.
 */
(function () {
    StopWatch::timer('build-container');
    /** @var \Psr\Container\ContainerInterface $container */
    $container = require 'config/container.php';
    StopWatch::timer('build-container');

    /** @var \Axleus\DevTools\Application $app */
    $app = $container->get(\Mezzio\Application::class);
    $factory = $container->get(\Mezzio\MiddlewareFactory::class);

    // Execute programmatic/declarative middleware pipeline and routing
    // configuration statements
    StopWatch::timer($app::class . '::pipe');
    (require 'config/pipeline.php')($app, $factory, $container);
    StopWatch::timer($app::class . '::pipe');
    StopWatch::timer($app::class . '::route');
    (require 'config/routes.php')($app, $factory, $container);
    StopWatch::timer($app::class . '::route');
    $app->run();
})();
