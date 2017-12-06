#!/usr/bin/env php
<?php
// Dial trunk options b(hangup-hook^install^1)
//
// Required dial plan contexts
//
// [hangup-hook]
// exten => install,1,Set(CHANNEL(hangup_handler_wipe)=hangup-hook,run,1)
// same => n,Return()
// exten => run,1,System(/var/lib/asterisk/bin/apel_recent.php --action=store --src="${FROMEXTEN}" --dst="${CALLERID(num)}" --disposition="${CDR(disposition)}" 2>&1 | tee -a /tmp/asterisk.php.log)
// same => n,Return()
// 
// [apel_recent]
// exten => s,1,Noop(Verific apel recent)
// same => n,Set(apel_recent=${SHELL(/var/lib/asterisk/bin/apel_recent.php --action=get --number="${CALLERID(num)}")})
// same => n,GotoIf($["${apel_recent}"=""]?ext-group,10,1)
// same => n,Gosub(apel_manager,${apel_recent},1)

$o = getopt('', array(
    'action:',
    'src:',
    'dst:',
    'disposition:',
    'number:',
));

// How much time store recent calls
$minutes_store = 120;
$debug = 1;
ini_set('date.timezone', 'Europe/Chisinau');
ini_set('display_errors', 'On');
error_reporting(E_ALL && ~E_NOTICE);

// +------------------------------+//
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

if ($o['action'] == 'store') {
    // ignoram apelurile fara raspuns
    if ($o['dst'] != '' && $o['disposition'] == 'ANSWERED') {
        if ($debug) debug("apel cu raspuns");
        exit;
    }
    if (preg_match("/^[0-9]{1,4}$/", $o['src']) && $o['dst'] != '') {
        $redis->set('sip_recent_call_' . $o['dst'], trim($o['src']) , $minutes_store * 60);
        if ($debug) debug("store number " . $o['dst'] . " for " . $minutes_store . " minutes");
        exit;
    }
    if ($debug) debug("ignore number " . $o['dst']);
    exit;
}
elseif ($o['action'] == 'get') {
    $result = $redis->get('sip_recent_call_' . $o['number']);
    if ($result == false) {
        return;
    }
    echo trim($result);
}

function debug($msg) {
    $date = date('d.m.Y H:i:s');
    echo "$date: $msg\n";
}

// +------------------------------+//

?>
