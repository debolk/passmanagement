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
     * Response code constants
     */
    const PASS_OKAY             = 'pass_okay';
    const ERROR_PASS_MISMATCH   = 'pass_mismatch';
    const ERROR_ENTRIES_TOO_OLD = 'entries_too_old';

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

    /**
     * Validate that the last two pass entries are correct
     * 1) last two pass scans are identical
     * 2) last two pass scans are maximum 10 mins before now
     * @return string one of the constants above
     */
    public function validatePassAttempt()
    {
        // Get the last two failures from the database
        $candidates = $this->medoo->select('attempts', ['timestamp', 'card_id'], [
            'access_granted' => false,
            'LIMIT' => 2,
            'ORDER' => 'timestamp DESC'
        ]);

        $pass_first = $candidates[0]['card_id'];
        $pass_second = $candidates[1]['card_id'];
        $time_second = $candidates[1]['timestamp'];

        // Last two passes must match
        if ($pass_first !== $pass_second) {
            return self::ERROR_PASS_MISMATCH;
        }

        // Timestamp must be no later than ten minutes ago
        $deadline = (new \DateTime())->sub(new \DateInterval('PT10M'));
        $entry = new DateTime($time_second);
        if ($entry < $deadline) {
            return self::ERROR_ENTRIES_TOO_OLD;
        }

        // All passed
        return self::PASS_OKAY;
    }

    /**
     * Return the last valid pass ID
     * @return string full ID of the pass
     */
    public function getLastRefusedPass()
    {
        // Get the last failure from the database
        $query = $this->medoo->select('attempts', ['card_id'], [
            'access_granted' => false,
            'LIMIT' => 1,
            'ORDER' => 'timestamp DESC'
        ]);

        return $query[0]['card_id'];
    }

    /**
     * Return an associative array of [username] => [timestamp]
     * which contains the last timestamp on which a specific username
     * successfully opened the door
     *
     * @return array
     */
    public function getLastEntries()
    {
        // Query for usernames and last timestamps of access
        $query = $this->medoo->query(
            'SELECT `username`, MAX(`timestamp`) AS last_entry
            FROM `attempts`
            WHERE `username` IS NOT NULL
            AND `access_granted` = 1
            GROUP BY `username`'
        )->fetchAll();

        // Construct associative array
        $output = [];
        $tz = new DateTimeZone('Europe/Amsterdam');
        $one_week_ago = new DateTime('-1 week', $tz);
        $one_month_ago = new DateTime('-1 month', $tz);

        foreach ($query as $row) {
            // Convert to human diff (1 month ago, 1 week ago, etc..)
            $entry = new DateTime($row['last_entry'], $tz);
            $result = '';

            if (!$entry) {
                $result = 'Voor 1 september 2015 (of nooit)';
            }
            elseif ($entry < $one_month_ago) {
                $result = 'Meer dan een maand geleden';
            }
            elseif ($entry < $one_week_ago) {
                $result = 'Meer dan een week geleden';
            }
            else {
                $result = 'Minder dan een week geleden';
            }

            $output[$row['username']] = $result;
        }

        return $output;
    }
}
