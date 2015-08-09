<?php

/**
 * The Error-class outputs fully-formatted JSON-objects that
 * have all the necessary data for processing errors on the client.
 */
class Error
{
    /**
     * Configuration objecy
     * @var array
     */
    private $config;

    /**
     * Setup the class
     * @param array $config configuration objecy
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Error handling function: log failures and return JSON-encoded error messages
     * INVOKING THIS FUNCTION STOPS PROCESSING (exit)
     *
     * @param  integer $http_code  HTTP status to send to the client
     * @param  string $error_code internal application error code
     * @param  string $title      descriptive title of the error message
     * @param  string $details    longer description of the cause of the error
     * @return void
     */
    function send($http_code, $error_code, $title, $details)
    {
        // Log the error details
        error_log("HTTP $http_code Code $error_code Error $title - $details");

        // Send standard error response to client
        http_response_code($http_code);
        echo json_encode([
            'code'    => $error_code,
            'title'   => $title,
            'details' => $details,
            'href'    => $this->config['base_url'] . 'docs.html#'.$error_code
        ]);
        exit;
    }
}
