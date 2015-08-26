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
require '../Database.php';

// Start classes we need to work
$error    = new Error($config['application']);
$oauth    = new OAuth($config['oauth']);
$ldap     = new LDAP($config['ldap']);
$deur     = new Deursoos($config['deursoos']);
$database = new Database($config['database']);

// All responses of this API are valid JSON
header('Content-Type: application/json');

// Validate we have a proper access token
if (! isset($_GET['access_token'])) {
    $error->send(401, 'oauth_token_missing', 'Missing OAuth token', 'Client must supply a valid OAuth2 access token with board-level permissions');
}
if (! $oauth->validToken($_SERVER['REQUEST_URI'], $_GET['access_token'])) {
    $error->send(403, 'oauth_token_invalid', 'OAuth token invalid', 'Access token is invalid, has expired, or does not have sufficient access privileges');
}

// Setup the LDAP connection
if (!$ldap->connect()) {
    $error->send(502, 'ldap_unavailable', 'LDAP server not responding', 'The API cannot connect to the LDAP server');
}
if (! $ldap->login()) {
    $error->send(500, 'ldap_login_failure', 'Cannot login to LDAP server', 'The API cannot login to the LDAP server');
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

    // Check the scanned pass, returning errors when not acceptable
    $scan = $deur->validatePassAttempt();
    if ($scan === Deursoos::ERROR_DOOR_RESPONSE_NOT_OKAY) {
        $error->send(502, $scan, 'Cannot connect to door', 'The door is unreachable. Last scanned pass could not be retrieved.');
    }
    elseif ($scan === Deursoos::ERROR_ENTRIES_TOO_OLD) {
        $error->send(403, $scan, 'Pass scan has expired', 'The last pass was scanned more than 10 minutes ago.');
    }
    elseif ($scan === Deursoos::ERROR_PASS_MISMATCH) {
        $error->send(403, $scan, 'Last two passes are not identical', 'The last two passes that were scanned are not the same pass.');
    }

    // Store pass on user
    $pass = $ldap->addPass($uid, $deur->getLastRefusedPass());

    // Send answer based on result
    if ($pass === LDAP::ERROR_USER_NOT_FOUND) {
        $error->send(404, $pass, 'The user cannot be found', 'This user does not exist or has been removed.');
    }
    elseif ($pass === LDAP::ERROR_DOUBLE_PASS) {
        $error->send(409, $pass, 'The user already has a pass', 'This user already has a pass set. A second one cannot be added.');
    }
    elseif ($pass === LDAP::ERROR_PASS_EXISTS) {
        $error->send(409, $pass, 'This pass is in use', 'Another user has registered this pass. It cannot be added again.');
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

// Check whether a specific pass can gain entry
$app->get('/deur/access/:pass', function($cardID) use ($app, $ldap, $error, $database) {

    // Find card information in LDAP
    $info = $ldap->infoOnPassAttempt($cardID);

    // Log attempt
    $database->logAttempt($cardID, $info['access'], $info['username'], $info['reason']);

    // Send appropriate response
    if ($info['access'] === true) {
        // No answer is needed
        $app->response->setStatus(204); // HTTP 204 No Content
    }
    else {
        $error->send(403, 'access_denied', 'Access denied', 'This pass may not open the door at this time.');
    }
});

// Run the application
$app->run();
