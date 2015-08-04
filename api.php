<?php

// We can show any and all errors
// as this is a board-only system
// i.e. trusted users only
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start Slim
require 'vendor/autoload.php';
$app = new \Slim\Slim();

// JSON-encoded data of all current members with passes
$app->get('/users', function(){
    return json_encode([
        ['uid' => 'jakob', 'name' => 'Jakob Buis', 'pass' => true, 'access' => true],
        ['uid' => 'inge', 'name' => 'Inge de Bruin', 'pass' => true, 'access' => false]
    ]);
});

// Grant a user access to the door
$app->post('/users/{uid}', function(){

});

// Deny a user access to the door
$app->delete('/users/{uid}', function(){

});

// Add a pass to a user
$app->post('/users/{uid}/pass', function(){

});

// Remove the pass of a user
$app->delete('/users/{uid}/pass', function(){

});

// Run the application
$app->run();
