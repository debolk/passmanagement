<?php

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
     * @param  string  $access_token OAuth2-access token
     * @return boolean               true if valid
     */
    public function validToken($access_token)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $this->config['authorisation_server']
                                          .$this->config['resource']
                                          .'?access_token='.$access_token);
        curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return ($status == 200);
    }
}
