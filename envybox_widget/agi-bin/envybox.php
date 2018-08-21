#!/usr/bin/php -q
<?php
require('phpagi.php');

$agi = new AGI();
$CNUM = $argv[1];
$CEXT = $argv[2];
$agi->set_variable("CNUM", $CNUM);
$agi->set_variable("CEXT", $CEXT);

if($argv[5] == 'C' || $argv[5] == 'OC') {
	$direction = 'incoming';
	$destination = 'null';
	if($argv[5] == 'OC') {
		$direction = 'outgoing';
		$destination = $argv[2];
	}

	$data = array(
	    "calldate" => time(),
	    "direction" => $direction,
	    "source" => $argv[1],
	    "destination" => $destination,
	    "duration" => 'null',
	    "status" => 'null',
	    "record_url" => 'null',
	    "event" => "START",
	    "uid" => $argv[3]
	);
	send2envybox($data);
} elseif($argv[5] == 'H' || $argv[5] == 'OH') {
	$direction = 'incoming';
	$destination = 'null';
	$record_url = null;
    $duration = null;

	if($argv[4] == 'ANSWER') {
		$record_url = ($argv[9]) ? "https://sip.loc/callapi.php?a=gertrecord&callid=".$argv[3] : null;
		$duration = $argv[8];
		$dstchannel = explode('/', $argv[6]);
		$arChanel = explode('-', $dstchannel);
		$destination = $arChanel[0];
	}

	if($argv[5] == 'OH') {
		$direction = 'outgoing';
		$destination = $argv[2];
	}
	
	$data = array(
	    "calldate" => strtotime($argv[7]),
	    "direction" => $direction,
	    "source" => $argv[1],
	    "destination" => $destination,
	    "duration" => $duration,
	    "status" => $argv[4],
	    "record_url" => $record_url,
	    "event" => "END",
	    "uid" => $argv[3],
	);
	send2envybox($data);
}


function send2envybox($data) {
    $opts = array('http' => array('method'  => 'POST','header'  => 'Content-type: application/x-www-form-urlencoded','content' => http_build_query($data)));
    return file_get_contents('https://DOMAIN.envycrm.com/hook/callapi/', false, stream_context_create($opts));
}

function getrecord($uid) {
    $PDO = new PDO('mysql:host=localhost;dbname=asteriskcdrdb', 'USER', 'PASS');
    $result = $PDO->query("SELECT * FROM cdr WHERE uniqueid='$uid'");
    return $result->fetch(2);
}
