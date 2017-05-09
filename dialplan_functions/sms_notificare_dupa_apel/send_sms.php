#!/usr/bin/env php
<?php

// System(/var/lib/asterisk/bin/send_sms.php --src="${CDR(src)}" --did="${CDR(dnid)}" --dst="${CDR(dstchannel)}" --disposition="${CDR(disposition)}" --context="${CONTEXT}")
// ./send_sms.php $CDR(src) $CDR(dst) $CDR(disposition) $CONTEXT

$o = getopt('', array('src:','did:','dst:','disposition:','context:'));
var_dump($o);

//+------------------------------+//

$ADDRESS="192.168.10.194";
$PASSWORD="cxyqAwbWyAdWEs";
$NUMBER="078074455";
$MSG="Apel telefonic: SRC:".$o['src']." DID:".$o['did']." (".$o['dst']."@".$o['context'].") Rezultat: ".$o['disposition'];
$GSMLINE=1;
$TRIES=3; // tries to send sms
$DELAY=2; // delay in seconds

//+------------------------------+//

error_log($MSG."\n", 3, "/tmp/send_sms.php.log");

mail('admin@iphost.md', 'Call info '.$o['src'].' => '.$o['dst'] , $MSG);

exit;

// create a new cURL resource
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://".$ADDRESS."/default/en_US/send.html?u=admin&p=".$PASSWORD."&l=".$GSMLINE."&n=".$NUMBER."&m=".urlencode($MSG));
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

while($TRIES>0) {
	$output = curl_exec($ch);
	$output = trim($output);
	list($result,$msg) = explode(',', $output);
	var_dump($output);
	if($result == 'Sending') {
		print "successfully sent\n";
		break;
	} else {
		print "try again \n";
	}
	$TRIES--;
	sleep($DELAY);
}
curl_close($ch);
?>