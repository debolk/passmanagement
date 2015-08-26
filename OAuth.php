<?php

/**
 * The OAuth-class validates the access tokens used to access the API.
 * The API operates as a resource server, granting access only when a
 * token is presented that is valid for the resource specified in
 * config.php, ldap > oauth > resource.
 */
class OAuth
{
    private $config;

    /**
     * Setup LDAP class, does not connect or login
     * @param array $config LDAP-configuration
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Validate the access level of the OAuth2 token
     * @param  string  $uri          the URI used to request the API
     * @param  string  $access_token OAuth2-access token
     * @return boolean               true if valid
     */
    public function validToken($uri, $access_token)
    {
        // One URL has a lower access level requirement
        if (strpos($uri, '/deur/access/') === 0) {
            return $this->validDeviceToken($access_token);
        }
        else {
            return $this->validBoardToken($access_token);
        }
    }

    /**
     * Check whether the provided token is valid
     * @return boolean true if valid, false in all other cases
     */
    private function validDeviceToken($access_token)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $this->config['authorisation_server'].'resource?access_token='.$access_token);
        curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return ($status == 200);
    }

    /**
     * Check whether the provided token is valid for board-level actions
     * @return boolean true if valid, false in all other cases
     */
    private function validBoardToken($access_token)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $this->config['authorisation_server'].$this->config['resource'].'?access_token='.$access_token);
        curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return ($status == 200);
    }
}
