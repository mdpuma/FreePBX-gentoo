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
// exten => s,1,System(/var/lib/asterisk/bin/hangup_handler.php --action=notifynow --src="${CALLERID(num)}" --srcname="${CALLERID(name)}" --did="${CDR(dnid)}" --dst="${CDR(dstchannel)}" --disposition="${DIALSTATUS}" --context="${CDR(dcontext)}" --department="sales" >&1 | tee -a /tmp/asterisk.php.log)
// same => n,Return()


require_once 'PHPMailer/class.phpmailer.php';
require_once 'PHPMailer/class.smtp.php';
require_once 'hangup_handler.class.php';

// +------------------------------+//

$config = array(
    'email' => array(
        'from' => 'sip@sip.local',
        'use_smtp' => 0,
        'smtphost' => '',
        'username' => '',
        'password' => '',
        'default_destination' => 'sip@sip.local',
        'departments' => [
            'sales-workhours' => 'sip@sip.local',
            'sales' => 'sip@sip.local',
            'service-workhours' => 'sip@sip.local',
            'service' => 'sip@sip.local',
            'tech' => 'sip@sip.local',
        ]
    ),
    
    // for email notification about lost calls by individual pair of manager=> email
    'managers_file' => '/var/lib/asterisk/bin/managers.csv',
    'telegram' => array(
        'script' => '/var/lib/asterisk/bin/telegram_bot.php',
        'default_destination' => -303442075,
        'departments' => [
            'sales' => -303442075,
            'service' => -276061837,
            'tech' => -288296707
//             'sales' => -303442075,
//             'service' => -276061837,
//             'tech' => -288296707
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

    if(isset($o['department']) && $o['department']!='') {
        $missedcall_file = '/tmp/missedcall_'.$o['department'].'.csv';
    } else {
        $missedcall_file = '/tmp/missedcall.csv';
    }
    store_missed_call($o, $missedcall_file);
}
elseif ($o['action'] == 'sendemail') {
    foreach($config['email']['departments'] as $department => $destination) {
        $missedcall_file = '/tmp/missedcall_'.$department.'.csv';
        $list = explode(",", $destination);
        foreach($list as $email) {
            $return = send_missed_call_email($email, $config, $missedcall_file);
            if(!$return) debug("Mailer Error: " . $mail->ErrorInfo);
        }
        @unlink($missedcall_file);
    }
    
    // send missedcall list for default_destination if exists
    $missedcall_file = '/tmp/missedcall.csv';
    if(is_file($missedcall_file)) {
        $return = send_missed_call_email($config['email']['default_destination'], $config, $missedcall_file);
        if(!$return) debug("Mailer Error: " . $mail->ErrorInfo);
        else @unlink($missedcall_file); 
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

// +------------------------------+//

?>
