#!/usr/bin/env php
<?php
// Required contexts:

// [cidlookup]
// exten => cidlookup_1,n,Gosub(predial-hook2,s,1)
//
// [predial-hook2]
// exten => s,1,Set(CHANNEL(hangup_handler_wipe)=hangup-hook2,s,1)
// same => n,Return()
// 
// [hangup-hook2]
// exten => s,1,System(/var/lib/asterisk/bin/email_reminder_by_csv.php --action=notifynow --src="${CALLERID(num)}" --srcname="${CALLERID(name)}" --did="${CDR(dnid)}" --dst="${CDR(dstchannel)}" --disposition="${DIALSTATUS}" --context="${CDR(dcontext)}" --department="sales" >&1 | tee -a /tmp/asterisk.php.log)
// same => n,Return()


require_once 'PHPMailer/class.phpmailer.php';
require_once 'PHPMailer/class.smtp.php';

// +------------------------------+//

$config = array(
    'email' => array(
        'from' => 'sip@sip.local',
        'to' => 'sip@sip.local',
        'use_smtp' => 0,
        'smtphost' => '',
        'username' => '',
        'password' => '',
    ),
    'missedcall_file' => '/tmp/apeluri_pierdute.csv',
    
    // for email notification about lost calls by individual pair of manager=> email
    'managers_file' => '/var/lib/asterisk/bin/managers.csv',
    'telegram' => array(
         'script' => '/var/lib/asterisk/bin/telegram_bot.php',
//        'script' => '/var/lib/asterisk/bin/telegram.php',
        'destination' => 'sales',
        'departments' => [
            'sales' => 'sales',
        ]
    ),
    'debug' => 1,
);

// +------------------------------+//

ini_set('date.timezone', 'Europe/Chisinau');
ini_set('display_errors', 'On');
error_reporting(E_ALL & ~E_NOTICE);

$o = getopt('', array(
    'action:',
    'src:',
    'srcname:',
    'did:',
    'dst:',
    'disposition:',
    'context:',
    'department:',
));

// +------------------------------+//

if ($o['action'] == 'store') {
    // check if this is unanswered call
    check_is_missing($o);

    $file_exists = 0;
    if (is_file($config['missedcall_file'])) $file_exists = 1;
    $fp = fopen($config['missedcall_file'], 'a+');
    if ($file_exists == 0) fwrite($fp, '"ora-data";"numar client";"numar apelat";"dispozitia"' . "\n");
    fwrite($fp, '"' . date("d.m.Y H:i:s") . '";"' . $o['src'] . '";"' . $o['did'] . '";"' . $o['disposition'] . "\"\n");
    fclose($fp);
}
elseif ($o['action'] == 'sendemail') {
    $mail = new PHPMailer();
    if ($EMAIL_SMTP == 1) {
        $mail->isSMTP();
        $mail->Host = $config['email']['smtphost'];
        $mail->Port = 587;
        $mail->SMTPSecure = '';
        $mail->SMTPAuth = true;
        $mail->Username = $config['email']['username'];
        $mail->Password = $config['email']['password'];
        // $mail->SMTPDebug = 2;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false));
    }

    $mail->From = $config['email']['from'];
    $mail->FromName = 'FreePBX';
    $mail->Subject = 'PBX: apeluri pierdute';
    $emails = explode(',', $config['email']['to']);
    foreach ($emails as $i) $mail->AddAddress($i);

    if (!is_file($config['missedcall_file'])) {
        $mail->Body = 'Nu sunt inregistrate apeluri pierdute';
    }
    else {
        $mail->Body = 'Gasiti atasament';
        $mail->AddAttachment($config['missedcall_file'], basename($config['missedcall_file']));
    }

    if (!$mail->send()) {
        if($config['debug']==1) debug("Mailer Error: " . $mail->ErrorInfo);
    }
    else {
        if($config['debug']==1) debug("Message sent!");
        @unlink($config['missedcall_file']);
    }
}
elseif ($o['action'] == 'notifynow') {
    // check if this is unanswered call
    check_is_missing($o);

//    send_email();
    send_telegram_msg(get_telegram_chat($o['department']), get_missedcall_template());
}
elseif ($o['action'] == 'force_notify') {
    send_telegram_msg(get_telegram_chat($o['department']), "Intrare apel pe numarul ${o['did']} de la ${o['src']} (${o['srcname']})");
}

function check_is_missing($o) {
    global $config;
    if ($o['dst'] != '' && ($o['disposition'] == 'ANSWERED' || $o['disposition'] == 'BUSY' || $o['disposition'] == 'ANSWER')) {
        if($config['debug']==1) debug("apel cu raspuns");
        exit;
    }
    if (!isset($o['srcname']) || empty($o['srcname'])) {
        $o['srcname'] = $o['src'];
    }
    if (preg_match("/^[0-9]{1,3}$/", $o['src'])) {
        if($config['debug']==1) debug("apel de iesire");
        exit;
    }
}

function send_telegram_msg($chat, $message) {
    global $config;
    
    // using telegram BotApi
    system($config['telegram']['script'].' --action=sendmessage --chat="'.$chat.'" --msg="'.$message.'"');
    
    // using telegram-cli
//     system('echo "'.$message.'" | '.$config['telegram']['script'].' "'.$chat.'"');
}

function get_telegram_chat($department=null) {
    global $o, $config;
    if(isset($o['department']) && !empty($o['department']) && isset($config['telegram']['departments'][$o['department']])) {
        return $config['telegram']['departments'][$o['department']].'"';
    } else {
        return $config['telegram']['destination'];
    }
}

function send_email() {
    global $mail, $config, $o;
    $mail = new PHPMailer();
    if ($config['email']['use_smtp'] == 1) {
        $mail->isSMTP();
        $mail->Host = $config['email']['smtphost'];
        $mail->Port = 587;
        $mail->SMTPSecure = '';
        $mail->SMTPAuth = true;
        $mail->Username = $config['email']['username'];
        $mail->Password = $config['email']['password'];
        // $mail->SMTPDebug = 2;
        $mail->SMTPOptions = array('ssl' => array('verify_peer' => false));
    }

    $o['manager'] = get_manager_by_callerid($o['srcname']);
    
    $mail->From = $config['email']['from'];
    $mail->FromName = 'FreePBX';
    $mail->Subject = 'PBX: apel pierdut: ' . $o['src'];
    $mail->AddAddress(get_email_by_csv($o['manager'], $config['managers_file']));
    $mail->Body = get_missedcall_template();
    

    if (!$mail->send()) {
        if($config['debug']==1) debug("Mailer Error: " . $mail->ErrorInfo);
    }
    else {
        if($config['debug']==1) debug("Message sent!");
    }
}

function get_email_by_csv($extension, $csvfile) {
    global $config;
    $result = '';
    if (($handle = @fopen($csvfile, "r")) !== false) {
        while (($data = fgetcsv($handle, 100, ",")) !== false) {
            if (!empty($data[0]) && !empty($extension) && $data[0] == $extension) {
                // found required extension
                return $data[1];
            }
        }
        fclose($handle);
    }
    return $config['email']['to'];
}

function get_manager_by_callerid($callerid) {
    if(preg_match("/[^|]+ \| ([0-9]+)/", $callerid, $matches)) {
        return $matches[1];
    }
    return '';
}

function get_missedcall_template() {
    global $o;
    return <<<EOF
Apel pierdut, receptionat pe numarul ${o['did']} de la ${o['src']} (${o['srcname']}) 
EOF;
}

function debug($msg) {
    $date = date('d.m.Y H:i:s');
    echo "$date: $msg\n";
}

// +------------------------------+//

?>
