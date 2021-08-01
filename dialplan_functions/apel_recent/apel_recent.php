#!/usr/bin/env php
<?php

/*

Asterisk Outbound Trunk Dial Options: TB(hangup-hook-out^install^1)
Dial trunk options B(predial-hook^s^1)

Required dial plan contexts

 
[hangup-hook-out]
exten => install,1,Set(CHANNEL(hangup_handler_wipe)=hangup-hook-out,run,1)
same => n,Return()
exten => run,1,System(/var/lib/asterisk/bin/apel_recent.php --action=store --src="${AMPUSER}" --dst="${CONNECTEDLINE(num)}" --disposition="STORE" 2>&1)
same => n(label2),Return()

[apel_recent]
exten => s,1,Noop(Verific apel recent)
same => n,Set(apel_recent=${SHELL(/var/lib/asterisk/bin/apel_recent.php --action=get --number="${CALLERID(num)}")})
same => n,GotoIf($["${apel_recent}"=""]?label1)
same => n,Gosub(apel_manager,${apel_recent},1)
same => n(label1),Return()

*/

$o = getopt('', array(
    'action:',
    'number:',
    'src:',
    'srcname:',
    'did:',
    'dst:',
    'disposition:',
    'context:'
));

// How much time store recent calls
$minutes_store = 300;
$debug = 1;
ini_set('date.timezone', 'Europe/Chisinau');
ini_set('display_errors', 'Off');
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
