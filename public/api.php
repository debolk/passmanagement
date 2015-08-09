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
require '../Deursoos.php';
require '../Error.php';

// Start classes we need to work
$error = new Error($config['application']);
$oauth = new OAuth($config['oauth']);
$ldap  = new LDAP($config['ldap']);
$deur  = new Deursoos($config['deursoos']);

// All responses of this API are valid JSON
header('Content-Type: application/json');

// Validate we have a proper access token
if (! isset($_GET['access_token'])) {
    $error->send(403, 'oauth_token_missing', 'Missing OAuth token', 'Client must supply a valid OAuth2 access token with board-level permissions');
}
if (! $oauth->validToken($_GET['access_token'])) {
    $error->send(400, 'oauth_token_invalid', 'OAuth token invalid', 'Access token is invalid, has expired, or does not have board-level permissions');
}

// Setup the LDAP connection
if (!$ldap->connect()) {
    $error->send(502, 'ldap_error', 'LDAP server not responding', 'The API cannot connect to the LDAP server');
}
if (! $ldap->login()) {
    $error->send(502, 'ldap_error', 'Cannot login to LDAP server', 'The API cannot login to the LDAP server');
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
$app->post('/users/:uid', function($uid) use ($app, $ldap, $error) {
    if ($ldap->grantAccess($uid)) {
        $app->response->setStatus(204); // HTTP 204 No Content
    }
    else {
        $error->send(500, 'internal_error', 'Access grant failed', 'The API cannot grant access to this user. The exact error is unknown.');
    }
});

// Deny a user access to the door
$app->delete('/users/:uid', function($uid) use ($app, $ldap, $error) {
    if ($ldap->denyAccess($uid)) {
        $app->response->setStatus(204); // HTTP 204 No Content
    }
    else {
        $error->send(500, 'internal_error', 'Access grant failed', 'The API cannot deny access to this user. The exact error is unknown.');
    }
});

// Add a pass to a user
$app->post('/users/:uid/pass', function($uid) use ($app, $ldap, $deur, $error) {

    // Check the scanned pass
    $scan = $deur->validatePassAttempt();
    if ($scan !== Deursoos::PASS_OKAY) {
        $error->send(407, 'pass_scan_failed', 'Failed to validate pass', $scan);
    }

    // Store pass on user
    $pass = $ldap->addPass($uid, $deur->getLastRefusedPass());

    // Send answer based on result
    if ($pass === LDAP::ERROR_USER_NOT_FOUND) {
        $error->send(404, 'user_not_found', 'The user cannot be found', 'This user does not exist or has been removed.');
    }
    elseif ($user === LDAP::ERROR_DOUBLE_PASS) {
        $error->send(409, 'User already has a pass');
        $error->send(409, 'user_has_pass', 'The user already has a pass', 'This user already has a pass set. A second one cannot be added.');
    }
    else {
        // Return the new entry of the user
        $app->response->setStatus(200);
        echo json_encode($ldap->getUser($uid));
    }
});

// Remove the pass of a user
$app->delete('/users/:uid/pass', function($uid) use ($app, $ldap, $error) {

    if ($ldap->removePass($uid)) {
        $app->response->setStatus(204); // HTTP 204 No Content
    }
    else {
        $error->send(500, 'internal_error', 'Pass removal failed', 'The API cannot remove the pass of this user. The exact error is unknown.');
    }
});

// Check the last scanned pass was valid
$app->get('/deur/checkpass', function() use ($app, $ldap, $deur, $error) {
    echo json_encode(['check' => $deur->validatePassAttempt()]);
});

// Run the application
$app->run();
