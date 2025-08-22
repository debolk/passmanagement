<?php

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Router{

    private $config;

    private $error;

    private $ldap;

    private $database;

    public function __construct($config){
        $this->config = $config;
    }

    // Start classes we need to work
    private function setup(ResponseInterface $response) {

        $this->error = new JSONError($this->config['application']);
        $this->ldap = new LDAP($this->config['ldap']);
        $oauth = new OAuth($this->config['oauth']);
        
        syslog(LOG_DEBUG, "Connecting to database");

        try {
            $this->database = new Database($this->config['database']);
        } catch (Exception $e) {
            return $this->error->send($response, 500, 'database_unavailable', 'Cannot connect to database', 'Adapt configuration to be able to create a valid database connection');
        }

        syslog(LOG_DEBUG, "Validating access token");

        // Validate we have a proper access token
        if (! isset($_GET['access_token'])) {
            return $this->error->send($response, 401, 'oauth_token_missing', 'Missing OAuth token', 'Client must supply a valid OAuth2 access token with board-level permissions');
        }


        if (!$oauth->validToken($_SERVER['REQUEST_URI'], $_GET['access_token'])) {
            return $this->error->send($response, 403, 'oauth_token_invalid', 'OAuth token invalid', 'Access token is invalid, has expired, or does not have sufficient access privileges');
        }

        syslog(LOG_DEBUG, "Setting up LDAP");

        // Setup the LDAP connection
        if (!$this->ldap->connect()) {
            return $this->error->send($response, 502, 'ldap_unavailable', 'LDAP server not responding', 'The API cannot connect to the LDAP server');
        } elseif (!$this->ldap->login()) {
            return $this->error->send($response, 500, 'ldap_login_failure', 'Cannot login to LDAP server', 'The API cannot login to the LDAP server');
        }
        return true;
    }

    public function route(RequestInterface $request, ResponseInterface $response, $args) {
        $setup = $this->setup($response);
        if ($setup != true) {
            return $setup;
        }

        $path = $request->getMethod() . ':' . $request->getUri()->getPath();
        if (isset($args['uid'])) {
            $path = str_replace($args['uid'], 'uid', $path);
                    } 
        if (isset($args['pass'])) {
            $path = str_replace($args['pass'], 'pass', $path);
        }
        syslog(LOG_DEBUG, "Setup successfull, routing for " . $path);

        switch($path) {
            case 'GET:/users': return $this->get_users($response);
            case 'POST:/users/uid': return $this->grant_access($response, $args['uid']);
            case 'DELETE:/users/uid': return $this->deny_access($response, $args['uid']);
            case 'POST:/users/uid/pass': return $this->add_user_pass($response, $args['uid']);
            case 'DELETE:/users/uid/pass': return $this->delete_user_pass($response, $args['uid']);
            case 'GET:/deur/checkpass': return $this->check_pass($response);
            case 'GET:/deur/access/pass': return $this->check_pass_access($response, $args['pass']);
        }
        return $this->error->send(500, "internal_error", "URL parsing error", "Could not parse URI path properly.");
    }
    
    private function get_users(ResponseInterface $res) {
        
        // Construct required data
        $users = $this->ldap->getAllUsers();
        $timestamps = $this->database->getLastEntries();
        $data = array_map(function($user) use ($timestamps) {
            $user['last_entry'] = isset($timestamps[$user['uid']]) ?
                                    ($timestamps[$user['uid']]) :
                                    'niet recent';
            return $user;
        }, $users);

        $body = $res->getBody();
        $body->write(json_encode($data));
        return $res->withBody($body);
    }

// Grant a user access to the door
    private function grant_access(ResponseInterface $res, $uid) {
        if ($this->ldap->grantAccess($uid)) {
            return $res->withStatus(204); //HTTP 204 No Content
        }
        else {
            return $this->error->send($res, 500, 'internal_error', 'Access grant failed', 'The API cannot grant access to this user. The exact error is unknown.');
        }
    }

// Deny a user access to the door
    private function deny_access(ResponseInterface $res, $uid) {
        if ($this->ldap->denyAccess($uid)) {
            return $res->withStatus(204); //HTTP 204 No Content
        }
        else {
            return $this->error->send($res, 500, 'internal_error', 'Access grant failed', 'The API cannot deny access to this user. The exact error is unknown.');
        }
    }

// Add a pass to a user
    private function add_user_pass(ResponseInterface $res, $uid) {

        // Check the scanned pass, returning errors when not acceptable
        $scan = $this->database->validatePassAttempt();
        if ($scan === Database::ERROR_ENTRIES_TOO_OLD) {
            return $this->error->send($res, 403, $scan, 'Pass scan has expired', 'The last pass was scanned more than 10 minutes ago.');
        }
        elseif ($scan === Database::ERROR_PASS_MISMATCH) {
            return $this->error->send($res, 403, $scan, 'Last two passes are not identical', 'The last two passes that were scanned are not the same pass.');
        }

        // Store pass on user
        $pass = $this->ldap->addPass($uid, $this->database->getLastRefusedPass()); 

        // Send answer based on result
        if ($pass === LDAP::ERROR_USER_NOT_FOUND) {
            return $this->error->send($res, 404, $pass, 'The user cannot be found', 'This user does not exist or has been removed.');
        }
        elseif ($pass === LDAP::ERROR_DOUBLE_PASS) {
            return $this->error->send($res, 409, $pass, 'The user already has a pass', 'This user already has a pass set. A second one cannot be added.');
        }
        elseif ($pass === LDAP::ERROR_PASS_EXISTS) {
            return $this->error->send($res, 409, $pass, 'This pass is in use', 'Another user has registered this pass. It cannot be added again.');
        }
        else {
            // Return the new entry of the user
            $body = $res->getBody();
            $body->write(json_encode($this->ldap->getUser($uid)));
            return $res->withStatus(200)->withBody($body);
        }
    }

// Remove the pass of a user
    private function delete_user_pass(ResponseInterface $res, $uid) {
        if ($this->ldap->removePass($uid)) {
            return $res->withStatus(204); // HTTP 204 No Content
        }
        else {
            return $this->error->send($res, 500, 'internal_error', 'Pass removal failed', 'The API cannot remove the pass of this user. The exact error is unknown.');
        }
    }

// Check the last scanned pass was valid
    private function check_pass(ResponseInterface $res) {
        $body = $res->getBody();
        $body->write(json_encode(['check' => $this->database->validatePassAttempt()])); //check whether a specific pass can gain entry
        return $res->withStatus(200)->withBody($body);
    }

// Check is a pass may open the door
    private function check_pass_access(ResponseInterface $res, $cardID) {

        // Find card information in LDAP
        $info = $this->ldap->infoOnPassAttempt($cardID);

        // Log attempt
        $this->database->logAttempt($cardID, $info['access'], $info['username'], $info['reason']);

        // Send appropriate response
        if ($info['access'] === true) {
            // No answer is needed
            return $res->withStatus(204); // HTTP 204 No Content
        }
        else {
            return $this->error->send($res, 403, 'access_denied', 'Access denied', 'This pass may not open the door at this time.');
        }
    }
}