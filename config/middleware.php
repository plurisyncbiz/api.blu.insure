<?php

use App\Middleware\BearerAuthMiddleware;
use Slim\App;
use Middlewares\Whoops;
use Slim\Middleware\ErrorMiddleware;

return function (App $app) {
// Parse json, form data and xml
    $app->addBodyParsingMiddleware();
// Add the Slim built-in routing middleware
    $app->addRoutingMiddleware();
    //$app->add(BearerAuthMiddleware::class);
    $app->add(\App\Middleware\ExceptionMiddleware::class);
    $app->add(ErrorMiddleware::class);

// Add basic auth
    //$app->add(HttpBasicAuthentication::class);
//JWT parsing
//CORS

    //always add this last - pretty logs - turn off in production
    //$app->add(Whoops::class);
// Handle exceptions




};