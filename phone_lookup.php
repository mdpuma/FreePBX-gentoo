<?php

require_once '/var/www/amplica.md/billing/configuration.php';

$pdo2 = new PDO("mysql:host=$db_host;dbname=$db_name", $db_username, $db_password);
$stmt2 = $pdo2->prepare("SELECT firstname,lastname,companyname from tblclients");
$stmt2->execute();

