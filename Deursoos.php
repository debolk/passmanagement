<?php

/**
 * The Deursoos class handles communication with the physical
 * controller of the door, checking failures and retrieving passnumbers.
 */
class Deursoos
{
    /**
     * Response code constants
     */
    const PASS_OKAY                    = 'pass_okay';
    const ERROR_DOOR_RESPONSE_NOT_OKAY = 'door_response_not_okay';
    const ERROR_PASS_MISMATCH          = 'pass_mismatch';
    const ERROR_ENTRIES_TOO_OLD        = 'entries_too_old';

    /**
     * Configuration object
     * @var array
     */
    private $config;

    /**
     * Container for the last scanned pass ID
     * @var string
     */
    private $lastPassID;

    /**
     * Setup class
     * @param array $config configuration object for door
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Validate that the last two pass entries are correct
     * 1) last two pass scans are identical
     * 2) last two pass scans are maximum 10 mins before now
     * @return string one of the constants above
     */
    public function validatePassAttempt()
    {
        // Request all failures
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $this->config['failures']);
        $request = curl_exec($curl);

        // Abort on unsuccessful request
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status !== 200) {
            return self::ERROR_DOOR_RESPONSE_NOT_OKAY;
        }

        // Get the data of the last two lines
        preg_match("/(.+?\n.+?)$/", $request, $last_two_lines);

        // Filter the timestamps and card numbers
        $regex = preg_match("/(.* CEST 20[0-9]{2}): (.*)\n(.* CEST 20[0-9]{2}): (.*)/", $last_two_lines[1], $matches);
        $time_second = $matches[3];
        $pass_first  = $matches[2];
        $pass_second = $matches[4];

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
        $this->lastPassID = $pass_first;
        return self::PASS_OKAY;
    }

    /**
     * Return the last valid pass ID
     * @return string full ID of the pass
     */
    public function getLastRefusedPass()
    {
        return $this->lastPassID;
    }
}
