#!/usr/bin/env php
<?php

// System(/var/lib/asterisk/bin/send_sms.php --src="${CDR(src)}" --did="${CDR(dnid)}" --dst="${CDR(dstchannel)}" --disposition="${CDR(disposition)}" --context="${CDR(dcontext)}")
// ./send_sms.php --src="${CDR(src)}" --did="${CDR(dnid)}" --dst="${CDR(dstchannel)}" --disposition="${CDR(disposition)}" --context="${CDR(dcontext)}")

$o = getopt('', array('src:','did:','dst:','disposition:','context:'));

$ADDRESS="1.1.1.1";
$PASSWORD="xxxx";
$GSMLINE=1;
$TRIES=3; // tries to send sms
$DELAY=2; // delay in seconds

$OPERATORS=array(
	101 => array('name' => 'name', 'number' => '078XXXXXX'),
	102 => array('name' => 'name', 'number' => '078XXXXXX'),
	103 => array('name' => 'name', 'number' => '078XXXXXX'),
	104 => array('name' => 'name', 'number' => '078XXXXXX'),
	105 => array('name' => 'name', 'number' => '078XXXXXX'),
	106 => array('name' => 'name', 'number' => '078XXXXXX'),
	107 => array('name' => 'name', 'number' => '078XXXXXX'),
);

ini_set('date.timezone', 'Europe/Chisinau');
//+------------------------------+//

//ignoram apelurile fara raspuns
if($o['disposition'] != 'ANSWERED') {
	exit;
}

if(preg_match("/^[0-9]{1,3}$/", $o['src'])) { //apel de iesire
	$NUMBER = $o['did'];
	$o['operatorintern'] = $o['src'];
} elseif(preg_match("/^SIP\/([0-9]+)\-/", $o['dst'], $matches)) { //apel de intrare
	$NUMBER = $o['src'];
	$o['operatorintern'] = $matches[1];
} else {
	die("Can't find operator number\n");
}

$o['operatorname'] = $OPERATORS[ $o['operatorintern'] ]['name'];
$o['operatornumber'] = $OPERATORS[ $o['operatorintern'] ]['number'];

if(!isset($OPERATORS[$o['operatorintern']])) {
	die("operatorintern IS NULL\n");
}
if(!is_numeric($NUMBER)) {
	die("SMS DST NUMBER IS NULL\n");
}
if(!is_numeric($o['operatornumber'])) {
	die("operatornumber IS NULL\n");
}

$MSG = <<<EOF
Salut!
Sunt ${o['operatorname']}, consultantul Dvs. personal. 
Telefon de contact: ${o['operatornumber']}
Va asteptam pe Ismail 81/1, Centrul de Business Panorama, et.1
EOF;

$DATE=date('d-m-Y H:i');

//+------------------------------+//

//mail('a@a.a', 'Call info '.$o['src'].' => '.$o['dst'].': disposition: '.$o['disposition'], $MSG);
//exit;

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
