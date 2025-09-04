<?php

if(!isset($_GET['number']) && !preg_match('/^0[67][0-9]+$/', $_GET['number'])) {
	print json_encode(array('result' => 'error', 'error' => 'please enter number'));
	die();
}

$number = $_GET['number'];


if($fh = fopen("/var/spool/asterisk/outgoing/request_call".date('U').".call", "w+")) {
	// Local/s@myserver_incoming
	$output= <<<EOF
Channel: Local/sales@website_callback
CallerID: $number
MaxRetries: 0
RetryTime: 60
WaitTime: 60
Context: from-internal
Extension: $number
Priority: 1
EOF;
		
	$res = fwrite($fh, $output);
	fclose($fh);
	if($res==true) {
		print json_encode(array('result' => 'success'));
	} else
		print json_encode(array('result' => 'error'));
} else {
	print json_encode(array('result' => 'error', 'error' => 'cant open file'));
	die();
}
