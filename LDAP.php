<?php

/**
 * The LDAP class handles all communication with LDAP, formatting
 * responses and handling business logic (e.g. a user can have only
 * one pass at a time, a pass can belong to only one user).
 */
class LDAP
{
    /**
     * LDAP connection
     * @var resource
     */
    private $ldap;

    /**
     * Configuration object
     * @var array
     */
    private $config;

    /**
     * Response code constants
     */
    const ERROR_USER_NOT_FOUND  = 'user_not_found';
    const ERROR_DOUBLE_PASS     = 'user_already_has_a_pass';
    const ERROR_PASS_EXISTS     = 'pass_exists';

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

    /**
     * Decommission the ldap-connection
     */
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
     * Find the pass and access data of a specific user
     * @param  string $uid the user id to retrieve
     * @return array      a users information, containing uid, name, pass and access
     */
    public function getUser($uid)
    {
        $user = $this->findUser($uid);
        if (!$user) {
            return null;
        }

        $pass = $this->findPass($uid);
        if (!$pass) {
            return null;
        }

        return [
            'uid'    => $user['uid'][0],
            'name'   => $user['cn'][0],
            'pass'   => true,
            'access' => in_array('gosaIntranetAccount', $user['objectclass'])
        ];
    }

    /**
     * Add the access flag to a user
     * @param  string $uid the user ID to update
     * @return boolean
     */
    public function grantAccess($uid)
    {
        // Find the user
        $user = $this->findUser($uid);
        if (!$user) {
            return false;
        }

        // Determine if we need to update
        if (in_array('gosaIntranetAccount', $user['objectclass'])) {
            return;
        }

        // Add flag to user
        $patch = ['objectclass' => ['gosaIntranetAccount']];
        ldap_mod_add($this->ldap, $user['dn'], $patch);
        return true;
    }

    /**
     * Remove the access flag of a user
     * @param  string $uid user ID to update
     * @return boolean
     */
    public function denyAccess($uid)
    {
        // Find the user
        $user = $this->findUser($uid);
        if (!$user) {
            return false;
        }

        // Determine if we need to update
        if (! in_array('gosaIntranetAccount', $user['objectclass'])) {
            return;
        }

        // Remove flag from user
        $patch = ['objectclass' => ['gosaIntranetAccount']];
        ldap_mod_del($this->ldap, $user['dn'], $patch);
        return true;
    }

    /**
     * Add a new pass to a user
     * @param string $uid        the userID to add to
     * @param string $passNumber the full pass number
     * @return boolean           true if succesful, or a error constant otherwise
     */
    public function addPass($uid, $passNumber)
    {
        // User must exist
        $user = $this->findUser($uid);
        if (!$user) {
            return self::ERROR_USER_NOT_FOUND;
        }

        // User must not already have a pass
        $existing_pass = $this->findPass($uid);
        if ($existing_pass) {
            return self::ERROR_DOUBLE_PASS;
        }

        // Pass may not exist in the system for another user
        if ($this->passExists($passNumber)){
            return self::ERROR_PASS_EXISTS;
        }

        // Build new entry
        $dn = 'cn=ovchipkaart,' . $user['dn'];
        $entry = [
            'objectClass' => 'device',
            'cn' => 'ovchipkaart',
            'serialNumber' => $passNumber
        ];
        ldap_add($this->ldap, $dn, $entry);
        return true;
    }

    /**
     * Delete the pass of a user
     * @param  string $uid the user ID to remove from
     * @return boolean
     */
    public function removePass($uid)
    {
        $pass = $this->findPass($uid);
        if (!$pass) {
            return false;
        }
        ldap_delete($this->ldap, $pass['dn']);
        return true;
    }

    /**
     * Find a LDAP user by its uid
     * @param  string    $uid the user id to find
     * @return array          details of the user, or null if it doesn't exist
     */
    private function findUser($uid)
    {
        $search = ldap_search($this->ldap, $this->config['base_dn'], "(&(objectClass=inetOrgPerson)(uid={$uid}))", ['gosaIntranetAccount']);
        if (ldap_count_entries($this->ldap, $search) !== 1) {
            return null;
        }
        return ldap_get_entries($this->ldap, $search)[0];
    }

    /**
     * Find the pass based on a user id
     * @param  strin $uid the user ID
     * @return array      details of the pass, or null if it doesn't exist
     */
    private function findPass($uid)
    {
        $user = $this->findUser($uid);
        if ($user === null) {
            return null;
        }

        $pass = ldap_search($this->ldap, $user['dn'], "(&(objectClass=device)(cn=ovchipkaart))");
        if (ldap_count_entries($this->ldap, $pass) !== 1) {
            return null;
        }

        return ldap_get_entries($this->ldap, $pass)[0];
    }

    /**
     * Determine whether a passNumber has been registered in LDAP
     * @param  string $passNumber full pass number
     * @return boolean            true if the pass exists
     */
    private function passExists($passNumber)
    {
        $search = ldap_search($this->ldap, $this->config['base_dn'], "(&(objectClass=device)(cn=ovchipkaart)(serialNumber=$passNumber))");
        return (ldap_count_entries($this->ldap, $search) > 0);
    }
}
