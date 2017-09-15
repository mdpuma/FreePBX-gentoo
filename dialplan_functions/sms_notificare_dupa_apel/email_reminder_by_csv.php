#!/usr/bin/env php
<?php
// Required contexts:
//
// [cidlookup]
// exten => cidlookup_1,n,Gosub(predial-hook2,s,1)
//
// [predial-hook2]
// exten => s,1,Set(CHANNEL(hangup_handler_wipe)=hangup-hook2,s,1)
// same => n,Return()
//
// [hangup-hook2]
// exten => s,1,System(/var/lib/asterisk/bin/email_reminder_by_csv.php --action=notifynow --src="${CDR(src)}" --srcname="${CALLERID(name)}" --did="${CDR(dnid)}" --dst="${CDR(dstchannel)}" --disposition="${CDR(disposition)}" --context="${CDR(dcontext)}" --manager="${MANAGER_ID}" 2>&1 | tee -a /tmp/asterisk.php.log)
// same => n,Return()
require_once 'PHPMailer/class.phpmailer.php';
require_once 'PHPMailer/class.smtp.php';

$o = getopt('', array(
    'action:',
    'src:',
    'srcname:',
    'did:',
    'dst:',
    'disposition:',
    'context:',
    'manager:',
));
$EMAIL_FROM = 'sip@sip.aaa';
$EMAIL_TO = 'aa@aa.aa';

$EMAIL_SMTP = 0;
$EMAIL_USERNAME = '';
$EMAIL_PASSWORD = '';
$EMAIL_HOST = '';

$filedat = '/tmp/apeluri_pierdute.csv';

// for email notification about lost calls by individual pair of manager=> email
$managers_file = '/var/lib/asterisk/bin/managers.csv';

ini_set('date.timezone', 'Europe/Chisinau');
ini_set('display_errors', 'Off');
error_reporting(E_ALL);

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
    if ($EMAIL_SMTP == 1) {
        $mail->isSMTP();
        $mail->Host = $EMAIL_HOST;
        $mail->Port = 587;
        $mail->SMTPSecure = '';
        $mail->SMTPAuth = true;
        $mail->Username = $EMAIL_USERNAME;
        $mail->Password = $EMAIL_PASSWORD;
        // $mail->SMTPDebug = 2;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false
            )
        );
    }

    $mail->From = $EMAIL_FROM;
    $mail->FromName = 'FreePBX';
    $mail->Subject = 'PBX: apeluri pierdute';
    $emails = explode(',', $EMAIL_TO);
    foreach ($emails as $i) $mail->AddAddress($i);

    if (!is_file($filedat)) {
        $mail->Body = 'Nu sunt inregistrate apeluri pierdute';
    }
    else {
        $mail->Body = 'Gasiti atasament';
        $mail->AddAttachment($filedat, basename($filedat));
    }

    if (!$mail->send()) {
        echo "Mailer Error: " . $mail->ErrorInfo;
    }
    else {
        echo "Message sent!";
        @unlink($filedat);
    }
}
elseif ($o['action'] == 'notifynow') {
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

    $mail = new PHPMailer();
    if ($EMAIL_SMTP == 1) {
        $mail->isSMTP();
        $mail->Host = $EMAIL_HOST;
        $mail->Port = 587;
        $mail->SMTPSecure = '';
        $mail->SMTPAuth = true;
        $mail->Username = $EMAIL_USERNAME;
        $mail->Password = $EMAIL_PASSWORD;
        // $mail->SMTPDebug = 2;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false
            )
        );
    }

    if(empty($o['manager'])) {
        $o['manager'] = get_manager_by_callerid($o['srcname']);
    }
    
    $mail->From = $EMAIL_FROM;
    $mail->FromName = 'FreePBX';
    $mail->Subject = 'PBX: apel pierdut: ' . $o['src'];
    $mail->AddAddress(get_email_by_csv($o['manager'], $managers_file));

    $mail->Body = <<<EOF
Salut!
Apel pierdut de la ${o['src']} (${o['srcname']}) 
EOF;
    

    if (!$mail->send()) {
        echo "Mailer Error: " . $mail->ErrorInfo;
    }
    else {
        echo "Message sent!";
        @unlink($filedat);
    }
}

function get_email_by_csv($extension, $csvfile) {
    global $EMAIL_TO;
    $result = '';
    if (($handle = fopen($csvfile, "r")) !== false) {
        while (($data = fgetcsv($handle, 100, ",")) !== false) {
            if ($data[0] == $extension) {
                // found required extension
                return $data[1];
            }
        }
        fclose($handle);
    }
    return $EMAIL_TO;
}

function get_manager_by_callerid($callerid) {
    if(preg_match("/[^|]+ \| ([0-9]+)/", $callerid, $matches)) {
        return $matches[1];
    }
    return '';
}

// +------------------------------+//

?>
