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

    /**
     * Add a new pass to a user
     * @param string $uid  the userID to add to
     * @param string $pass the full pass number
     * @return void
     */
    public function addPass($uid, $pass)
    {
        $user = $this->findUser($uid);

        // Build new entry
        $dn = 'cn=ovchipkaart,' . $user['dn'];
        $entry = [
            'objectClass' => 'device',
            'cn' => 'ovchipkaart',
            'serialNumber' => $pass
        ];
        ldap_add($this->ldap, $dn, $entry);
    }

    /**
     * Delete the pass of a user
     * @param  string $uid the user ID to remove from
     * @return void
     */
    public function removePass($uid)
    {
        $pass = $this->findPass($uid);
        ldap_delete($this->ldap, $pass['dn']);
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

    /**
     * Find the pass based on a user id
     * @param  strin $uid the user ID
     * @return array      details of the pass
     * @throws Exception  when the user or the pass does not exist
     */
    private function findPass($uid)
    {
        $user = $this->findUser($uid);
        $pass = ldap_search($this->ldap, $user['dn'], "(&(objectClass=device)(cn=ovchipkaart))");
        if (ldap_count_entries($this->ldap, $pass) !== 1) {
            throw new Exception('This user has no pass');
        }
        return ldap_get_entries($this->ldap, $pass)[0];
    }
}
