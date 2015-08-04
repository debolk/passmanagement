<?php

/*
 * Bootstrapping
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../vendor/autoload.php';
require '../config.php';
require '../LDAP.php';
require '../OAuth.php';

// All responses of this API are valid JSON
header('Content-Type: application/json');

/**
 * Error handling function: log failures and return JSON-encoded error messages
 * INVOKING THIS FUNCTION STOPS PROCESSING (exit)
 *
 * @param  int    $status_code HTTP status code to return
 * @param  string $message     message to include. This is sent to the client!
 */
function fatalError($status_code, $message)
{
    error_log($message);
    http_response_code($status_code);
    echo json_encode(['error' => $message]);
    exit;
}

// Validate access token
$oauth = new OAuth($config['oauth']);
if (! isset($_GET['access_token'])) {
    fatalError(401, 'Must supply a valid OAuth2 access token with board-level permissions');
}
if (! $oauth->validToken($_GET['access_token'])) {
    fatalError(400, 'Access token is expired or invalid, or does not have board-level permissions');
}

// Setup LDAP
$ldap = new LDAP($config['ldap']);
if (!$ldap->connect()) {
    fatalError(502, 'Cannot connect to LDAP server');
}
if (! $ldap->login()) {
    fatalError(500, 'Cannot login to LDAP server using configured credentials');
}

/*
 * API endpoint definition
 */
$app = new \Slim\Slim();

// JSON-encoded data of all current members with passes
$app->get('/users', function() use ($ldap) {
    echo json_encode($ldap->getAllUsers());
});

// Grant a user access to the door
$app->post('/users/:uid', function($uid) use ($app, $ldap) {
    $ldap->grantAccess($uid);
    $app->response->setStatus(204); // HTTP 204 No Content
});

// Deny a user access to the door
$app->delete('/users/:uid', function($uid) use ($app, $ldap) {
    echo $ldap->denyAccess($uid);
    die;
    // $app->response->setStatus(204); // HTTP 204 No Content
});

// Add a pass to a user
$app->post('/users/:uid/pass', function($uid) use ($app, $ldap) {
    $ldap->addPass($uid);
    $app->response->setStatus(204); // HTTP 204 No Content
});

// Remove the pass of a user
$app->delete('/users/:uid/pass', function($uid) use ($app, $ldap) {
    $ldap->removePass($uid);
    $app->response->setStatus(204); // HTTP 204 No Content
});

// Run the application
$app->run();
