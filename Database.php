<?php

/**
 * The Database class handles abstraction of the MySQL database
 */
class Database
{
    /**
     * Database driver class
     * @var Medoo
     */
    private $medoo;

    /**
     * Setup class
     * @param array $config configuration object for the database
     */
    public function __construct($config)
    {
        $this->medoo = new medoo($config);
    }

    /**
     * Log a pass attempt to the database
     * @param  string  $cardID   the full card ID
     * @param  boolean $access   true if access was granted, false otherwise
     * @param  string  $username the username of the owner of the card, if known, defaults to null otherwise
     * @param  string  $reason   the reason for the decision, if known, defaults to null otherwise
     * @return void
     */
    public function logAttempt($cardID, $access, $username = null, $reason = null)
    {
        $this->medoo->insert('attempts', [
            'card_id'        => $cardID,
            'access_granted' => $access,
            'username'       => $username,
            'reason'         => $reason,
        ]);
    }
}
