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
        $search = ldap_search($this->ldap, $this->config['base_dn'], '(&(objectClass=device)(cn=ovchipkaart))', ['dn']);
        $cards = ldap_get_entries($this->ldap, $search);

        // Strip off initial 'count' entry
        array_shift($cards);

        return array_map(function($card){
            // Construct DN of the owner of the card
            $owner_dn = str_replace('cn=ovchipkaart,', '', $card['dn']);

            // Get the owner details
            $owner_ldap = ldap_read($this->ldap, $owner_dn, '(objectclass=inetOrgPerson)', ['uid', 'objectclass', 'cn']);
            $owner = ldap_get_entries($this->ldap, $owner_ldap);

            // Construct result
            return [
                'uid'    => $owner[0]['uid'][0],
                'name'   => $owner[0]['cn'][0],
                'pass'   => true,
                'access' => in_array('gosaIntranetAccount', $owner[0]['objectclass'])
            ];
        }, $cards);
    }

    /**
     * Add the access flag to a user
     * @param  string $uid the user ID to update
     * @return void
     */
    public function grantAccess($uid)
    {
        // Find the user
        $user = $this->findUser($uid);

        // Determine if we need to update
        if (in_array('gosaIntranetAccount', $user['objectclass'])) {
            return;
        }

        // Add flag to user
        $patch = ['objectclass' => ['gosaIntranetAccount']];
        ldap_mod_add($this->ldap, $user['dn'], $patch);

    }

    /**
     * Remove the access flag of a user
     * @param  string $uid user ID to update
     * @return void
     */
    public function denyAccess($uid)
    {
        // Find the user
        $user = $this->findUser($uid);

        // Determine if we need to update
        if (! in_array('gosaIntranetAccount', $user['objectclass'])) {
            return;
        }

        // Remove flag from user
        $patch = ['objectclass' => ['gosaIntranetAccount']];
        ldap_mod_del($this->ldap, $user['dn'], $patch);
    }

    public function addPass($uid, $pass)
    {
        // Find user
        // if already have pass
            // return throw error
        // add pass
    }

    public function removePass($uid)
    {
        // Find pass
        // remove pass
    }

    /**
     * Find a LDAP user by its uid
     * @param  string    $uid the user id to find
     * @return array          details of the user
     * @throws Exception      when user does not exist
     */
    private function findUser($uid)
    {
        $search = ldap_search($this->ldap, $this->config['base_dn'], "(&(objectClass=inetOrgPerson)(uid={$uid}))", ['gosaIntranetAccount']);
        if (ldap_count_entries($this->ldap, $search) !== 1) {
            throw new Exception('User does not exist');
        }
        return ldap_get_entries($this->ldap, $search)[0];
    }
}
