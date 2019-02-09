<?php

if(!isset($_GET['number'])) {
	print json_encode(array('result' => 'error', 'error' => 'please enter number'));
	die();
}

if($fh = fopen("/var/spool/asterisk/outgoing/alarm".date('U').".call", "w+")) {
	$output= <<<EOF
Channel: SIP/goip/1${_GET['number']}
CallerID: 999
MaxRetries: 0
RetryTime: 60
WaitTime: 15
Context: alarm
Extension: icinga
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
