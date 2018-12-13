#!/usr/bin/env php
<?php
// Required contexts:

/*

[cidlookup]
exten => cidlookup_1,n,Gosub(hangup-hook1,install,1)

; ring group
[hangup-hook1]
exten => install,1,Set(CHANNEL(hangup_handler_wipe)=hangup-hook1,run,1)
same => n,Return()
exten => run,1,noop(hangup-hook1)
same => n,System(/var/lib/asterisk/bin/roistat_webhook.php --src="${CALLERID(num)}" --did="${CDR(dnid)}" --dst="${CDR(dstchannel)}" --disposition="${DIALSTATUS}")
same => n,Return()

*/

// +------------------------------+//

$config = array(
    'debug' => 1,
);

// +------------------------------+//

ini_set('date.timezone', 'Europe/Chisinau');
ini_set('display_errors', 'On');
error_reporting(E_ALL & ~E_NOTICE);

$o = getopt('', array(
    'src:',
    'did:',
    'dst:',
    'disposition:',
));

// +------------------------------+//

if(!preg_match("/^ANSWER/", $o['disposition'])) {
	die("call without answer\n");
}

if(preg_match("/(PJ)?SIP\/([0-9]+)-/", $o['dst'], $matches)) {
	$extension = $matches[2];
}

$payload = json_encode(array(
	'manager-id' => $extension,
	'caller' => $o['src'],
	'callee' => $o['did']
));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://domain.com/roistat/asterisk.php');
curl_setopt($ch, CURLOPT_HEADER, TRUE);
curl_setopt($ch, CURLOPT_NOBODY, TRUE); // remove body
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
$head = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch); 

var_dump($payload);
var_dump($httpCode);

// +------------------------------+//

?>
