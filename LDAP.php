<?php

class LDAP
{
    private $ldap;
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
     * Connect to LDAP using the configuration provided
     * @return boolean true if connected
     */
    public function connect()
    {
        $this->ldap = @ldap_connect($this->config['host']);
        return ($this->ldap != false);
    }

    /**
     * Login to LDAP using the configuration provided
     * @return boolean true if the login is successful
     */
    public function login()
    {
        return @ldap_bind($this->ldap, $this->config['username'], $this->config['password']);
    }

    public function __destruct()
    {
        @ldap_close($this->ldap);
    }

    /**
     * Find all users which have passes in LDAP
     * @return array an user information, containing uid, name, pass and access
     */
    public function getAllUsers()
    {
        // Find all cards
        $search = ldap_search($this->ldap, 'dc=bolkhuis,dc=nl', '(&(objectClass=device)(cn=ovchipkaart))', ['dn']);
        $cards = ldap_get_entries($this->ldap, $search);

        // Strip off initial 'count' entry
        array_shift($cards);

        return array_map(function($card){
            // Construct DN of the owner of the card
            $owner_dn = str_replace('cn=ovchipkaart,', '', $card['dn']);

            // Get the owner details
            $owner_ldap = ldap_read($this->ldap, $owner_dn, '(objectclass=inetOrgPerson)', ['uid', 'objectclass', 'cn']);
            $owner = ldap_get_entries($this->ldap, $owner_ldap);

            // var_dump($owner);

            // Construct result
            return [
                'uid'    => $owner[0]['uid'][0],
                'name'   => $owner[0]['cn'][0],
                'pass'   => true,
                'access' => in_array('gosaIntranetAccount', $owner[0]['objectclass'])
            ];
        }, $cards);
    }
}
