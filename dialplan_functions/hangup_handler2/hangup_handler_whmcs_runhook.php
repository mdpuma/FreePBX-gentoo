<?php

// Including necessary files
require_once("init.php");

// Using WHMCS Capsule for database access
use WHMCS\Database\Capsule;


// read POST data
$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {    http_response_code(400);    echo json_encode(['error' => 'Empty request body']);    exit;}
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {    http_response_code(400);    echo json_encode(['error' => 'Invalid JSON', 'message' => json_last_error_msg()]);    exit;}
// $data is the decoded JSON object/arrayheader('Content-Type: application/json');echo json_encode(['ok' => true, 'data' => $data]);


// Checking if callerid is provided in the request
if (!isset($data['callerid'])) {
    die("No callerid provided");
}

// Cleaning and validating callerid
$callerid = preg_replace('/\s+/', '', $data['callerid']);
$callerid = substr($callerid, -8);
$full_callerid = $data['callerid'];

if (!is_numeric($callerid)) {
    die("Callerid is not numeric");
}

if (!isset($data['notification_identifier'])) {
    die('Missing notification_identifier');
}

$identifier = trim($data['notification_identifier']);

// Collect all GET parameters except excluded ones
$excluded_params = ['notification_identifier'];
$get_params = [];
foreach ($data as $key => $value) {
    if (!in_array($key, $excluded_params)) {
        $get_params[$key] = trim($value);
    }
}

// Pair _text and _value parameters, formatting phone numbers as tel: links
$paired_params = [];
foreach ($get_params as $key => $value) {
    if (preg_match('/_text$/', $key)) {
        $base_key = str_replace('_text', '', $key);
        $value_key = $base_key . '_value';

        if (array_key_exists($value_key, $get_params)) {
            $text = trim($get_params[$key]);
            $value = trim($get_params[$value_key]);
            $paired_params[$text] = $value;

            // Remove the _text and _value keys from get_params to avoid duplication
            unset($get_params[$key]);
            unset($get_params[$value_key]);
        }
    }
}

run_hook('TriggerCustomNotificationEvent', [
    'notification_identifier' => $identifier,
    'title' => (isset($data['title']) ? $data['title'] : ''),
    'custom_message' => (isset($data['custom_message']) ? $data['custom_message'] : 'Apel pierdut'),
    //'card_link' => $card_link,
    'get_params' => $paired_params,
    //'client_data' => $client_data,
    'callerid' => $full_callerid,
    'called' => $called
]);
