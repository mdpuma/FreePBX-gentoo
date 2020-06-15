#!/usr/bin/env php
<?php
// Required contexts:

/*

[cidlookup]
exten => cidlookup_1,n,Gosub(hangup-hook1,install,1)

; functional for ring group & queue
[hangup-hook1]
exten => install,1,Set(CHANNEL(hangup_handler_wipe)=hangup-hook1,run,1)
same => n,Return()
exten => run,1,noop(hangup-hook1)
same => n,GotoIf($["${QUEUENUM}"!="" && "${ABANDONED}"!="TRUE"]?label1)
same => n,System(/var/lib/asterisk/bin/hangup_handler.php --action=notifynow --src="${CALLERID(num)}" --srcname="${CALLERID(name)}" --did="${CDR(did)}" --dst="${CDR(dstchannel)}" --disposition="${DIALSTATUS}")
same => n(label1),Return()

[send_message]
exten => s,1,System(/var/lib/asterisk/bin/hangup_handler.php --action=send_message --src="${CALLERID(num)}" --srcname="${CALLERID(name)}" --did="${CDR(did)}" --dst="${CDR(dstchannel)}" --disposition="${DIALSTATUS}" --department="")

*/

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
//             'sales-workhours' => 'sip@sip.local',
//             'sales' => 'sip@sip.local',
        ]
    ),
    
    // for email notification about lost calls by individual pair of manager=> email
    'managers_file' => '/var/lib/asterisk/bin/managers.csv',
    'notification_server_url' => 'http://sip.iphost.md:3001',
    'telegram' => array(
        'default_destination' => ,
        'departments' => [
// 			'tech' => '',
        ]
    ),
    'slack' => array(
		'default_destination' => '', // pentru departament vinzari
		'departments' => [
// 	             'tech' => '',
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
    'department:',
    'message:',
));

// +------------------------------+//

$hangup_handler = new hangup_handler($config);

switch($o['action']) {
	case 'store': {
		// check if this is unanswered call
		$hangup_handler->check_is_missing($o);

		if(isset($o['department']) && $o['department']!='') {
			$missedcall_file = '/tmp/missedcall_'.$o['department'].'.csv';
		} else {
			$missedcall_file = '/tmp/missedcall.csv';
		}
		$hangup_handler->store_missed_call($o, $missedcall_file);
		break;
	}
	case 'sendemail': {
		foreach($config['email']['departments'] as $department => $destination) {
			$missedcall_file = '/tmp/missedcall_'.$department.'.csv';
			$list = explode(",", $destination);
			foreach($list as $email) {
				$return = $hangup_handler->send_missed_call_email_report($email, $missedcall_file);
				if(!$return) $hangup_handler->debug("Mailer Error: " . $mail->ErrorInfo);
			}
			@unlink($missedcall_file);
		}
		
		// send missedcall list for default_destination if exists
		$missedcall_file = '/tmp/missedcall.csv';
		if(is_file($missedcall_file)) {
			$return = $hangup_handler->send_missed_call_email_report($config['email']['default_destination'], $missedcall_file);
			if(!$return) $hangup_handler->debug("Mailer Error: " . $mail->ErrorInfo);
			@unlink($missedcall_file); 
		}
		break;
	}
	case 'notifynow': {
		// check if this is unanswered call
		$hangup_handler->check_is_missing($o);

//		send_missed_call_email(get_email_destination($o['department']));
		$hangup_handler->send_telegram_msg($o['department'], $hangup_handler->get_missedcall_template($o));
// 		$hangup_handler->send_slack_msg($o['department'], $hangup_handler->get_missedcall_template($o));
		break;
	}
	case 'send_message': {
// 		$hangup_handler->send_slack_msg($o['department'], $o['message']);
		$hangup_handler->send_telegram_msg($o['department'], "Intrare apel pe numarul ${o['did']} de la ${o['src']} (${o['srcname']})");
		break;
	}
}

// +------------------------------+//

?>
