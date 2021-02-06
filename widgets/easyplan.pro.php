<?php

@ini_set('display_errors', 'Off');

if (!isset($_GET['number'])) die ("query with number please");

$len = strlen($_GET['number']);
$len2 = 8;
$number = substr($_GET['number'], $len-$len2, $len2 );

$args = http_build_query(array('phone' => $number));
$url = 'https://api.easyplan.pro/apps/api/patients?'.$args;
$apikey = '';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	'X-EasyPlan-Utils: '.$apikey
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
curl_close($ch);

$response = json_decode($response);

if (isset($response->message)) {
	echo $_GET['number'];
} else {
	echo $response->fullName;
}
