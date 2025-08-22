<?php

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Seld\JsonLint\Undefined;
use Slim\Factory\AppFactory;

/*
 * Bootstrapping
 */
// error_reporting(E_ALL);
ini_set('display_errors', false);
ini_set('error_log', 'syslog');
ini_set('log_errors', true);
openlog('passmanagement', LOG_PID | LOG_PERROR, LOG_USER);

require '../vendor/autoload.php';
require '../config.php';
require '../LDAP.php';
require '../OAuth.php';
require '../Error.php';
require '../Database.php';
require '../Router.php';

// All responses of this API are valid JSON
header('Content-Type: application/json');

syslog(LOG_DEBUG, "Setting up API app");

/*
 * API endpoint definition
 */
$app = AppFactory::create();

// Define the router that will route the API calls
$router = new Router($config);

$app->get('/users', array($router, 'route'));
$app->post('/users/{uid}', array($router, 'route'));
$app->delete('/users/{uid}', array($router, 'route'));
$app->post('/users/{uid}/pass', array($router, 'route'));
$app->delete('/users/{uid}/pass', array($router, 'route'));
$app->get('/deur/checkpass', array($router, 'route'));
$app->get('/deur/access/{pass}', array($router, 'route'));

// Run the application
$app->run();
closelog();