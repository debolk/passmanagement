<?php

// We can show any and all errors
// as this is a board-only system
// i.e. trusted users only
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Require a valid OAuth token

// Define routes
$app->get('/passes', function(){

});

// Run the application
$app->run();
