<?php

// $asteriskcdrdb mysql data
$astmysql['host'] = 'localhost';
$astmysql['db']   = 'asteriskcdrdb';
$astmysql['user'] = 'freepbx';
$astmysql['pass'] = 'stXQ848THvgzBt';

require_once '/var/www/amplica.md/billing/configuration.php';

echo "Processing:";

$pdo1 = new PDO("mysql:host=${astmysql['host']};dbname=${astmysql['db']}", $astmysql['user'], $astmysql['pass']);
$pdo1->beginTransaction();

$pdo2 = new PDO("mysql:host=$db_host;dbname=$db_name", $db_username, $db_password);
$stmt2 = $pdo2->prepare("SELECT firstname,lastname,companyname,notes,phonenumber from tblclients");
$stmt2->execute();

$rows = $stmt2->fetchAll();
foreach ($rows as $c) {
	$name = $c['firstname'].' '.$c['lastname'].(!empty($c['companyname']) ? '/'.$c['companyname'] : '');
	$phone=$c['phonenumber'];
	
	// filter name
	$name = str_replace(
		array('&amp;','&quot;', '&#039;'),
		array('&', '\'', ''),
		$name
	);
	
	// parse numbers delimited with any kind of characters
	$phones = @preg_split('/[^0-9\- ]+/', $phone);
	if(!empty($phones)) foreach($phones as $p) {
		addToRedis($name, $p);
		continue 2;
	}
	
	// parse numbers delimited with ,
	$phones = @explode(',', $phone);
	if(!empty($phones)) {
		foreach($phones as $p) addToRedis($name, $p);
		continue 2;
	}
	
	// parse numbers delimited with /
	$phones = @explode(',', $phone);
	if(!empty($phones)) foreach($phones as $p) {
		addToRedis($name, $p);
		continue 2;
	}
	
	addToRedis($name, $phone);
}
$pdo1->commit();
exec("/usr/sbin/asterisk -x 'database deltree cidname'");
echo "\n";

function addToRedis($name, $phone) {
	global $pdo1;
	if(empty($phone)) return;
	
	// remove spaces
	$phone = str_replace(' ', '', $phone);
	$phone = str_replace('-', '', $phone);
	
	// filter numbers
	if(preg_match("/^[0-9]{6}$/", $phone)) {
		$phone='022'.$phone;
	} elseif(preg_match("/^373(.*)$/", $phone, $match)) {
		$phone='0'.$phone;
	} elseif(preg_match("/^6[0-9]{7}$/", $phone, $match)) {
		$phone='0'.$phone;
	} elseif(preg_match("/^7[0-9]{7}$/", $phone, $match)) {
		$phone='0'.$phone;
	} elseif(preg_match("/^22[0-9]{6}$/", $phone, $match)) {
		$phone='0'.$phone;
	} 
	
	// fix 0373/00373/373 to 0
	$phone = preg_replace('/^0373/', '0', $phone);
	$phone = preg_replace('/^00373/', '0', $phone);
	$phone = preg_replace('/^37322/', '022', $phone);
	
	if(empty($phone)) return;
	
	$stmt = $pdo1->prepare("INSERT INTO whmcs_phones (number, callername) VALUES (:n, :c) ON DUPLICATE KEY UPDATE callername=:c;");
	$stmt->bindParam(':n', $number);
	$stmt->bindParam(':c', $callername);
	$number = $phone;
	$callername = $name;
	$stmt->execute();
	echo " $phone";
} 
