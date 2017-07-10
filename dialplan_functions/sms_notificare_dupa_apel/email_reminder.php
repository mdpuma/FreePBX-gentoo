#!/usr/bin/env php
<?php
require_once 'PHPMailer/class.phpmailer.php';

// System(/var/lib/asterisk/bin/email_reminder.php --action=store --src="${CDR(src)}" --srcname="${CALLERID(name)}" --did="${CDR(dnid)}" --dst="${CDR(dstchannel)}" --disposition="${CDR(disposition)}" --context="${CDR(dcontext)}")

$o = getopt('', array(
    'action:',
    'src:',
    'srcname:',
    'did:',
    'dst:',
    'disposition:',
    'context:'
));
$EMAIL_FROM = 'sip@iphost.md';
$EMAIL_TO = 'admin@iphost.md';
$filedat = '/tmp/apeluri_pierdute.csv';
ini_set('date.timezone', 'Europe/Chisinau');

// +------------------------------+//

if ($o['action'] == 'store') {

    // ignoram apelurile fara raspuns

    if ($o['dst'] != '' && $o['disposition'] == 'ANSWERED') {
        die("apel cu raspuns");
    }

    if (!isset($o['srcname']) || empty($o['srcname'])) {
        $o['srcname'] = $o['src'];
    }

    if (preg_match("/^[0-9]{1,3}$/", $o['src'])) {
        die("apel de iesire");
    }

    if (is_file($filedat)) $file_exists = 1;
    else $file_exists = 0;
    $fp = fopen($filedat, 'a+');
    $date = date("d.m.Y H:i:s");
    if ($file_exists == 0) fwrite($fp, '"ora-data";"numar client";"numar apelat";"dispozitia"' . "\n");
    fwrite($fp, '"' . $date . '";"' . $o['src'] . '";"' . $o['did'] . '";"' . $o['disposition'] . "\"\n");
    fclose($fp);
}
elseif ($o['action'] == 'sendemail') {
    $mail = new PHPMailer();
    $mail->From = $EMAIL_FROM;
    $mail->FromName = 'FreePBX';
    $mail->Body = 'Gasiti atasament';
    $mail->Subject = 'PBX: apeluri pierdute';
    $emails = explode(',', $EMAIL_TO);
    foreach($emails as $i) $mail->AddAddress($i);
    $mail->AddAttachment($filedat, basename($filedat));
    if (!$mail->send()) {
        echo "Mailer Error: " . $mail->ErrorInfo;
    }
    else {
        echo "Message sent!";
    }
}

// +------------------------------+//

?>