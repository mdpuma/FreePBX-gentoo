#!/usr/bin/env php
<?php
// Dial trunk options B(predial-hook^s^1)
//
// Required dial plan contexts
//
/* 
[hangup-hook]

; for asterisk 11
exten => s,1,System(/var/lib/asterisk/bin/apel_recent.php --action=store --src="${CDR(src)}" --dst="${CDR(dst)}" --disposition="${CDR(disposition)}" --context="${CDR(dcontext)}" 2>&1 | tee -a /tmp/asterisk.php.log)

; for asterisk 13
exten => s,1,System(/var/lib/asterisk/bin/apel_recent.php --action=store --src="${CDR(cnum)}" --dst="${CALLERID(dnid)}" --disposition="${CDR(disposition)}" --context="${CDR(dcontext)}" 2>&1 | tee -a /tmp/asterisk.php.log)
same => n,Return()

[predial-hook]
exten => s,1,Set(CHANNEL(hangup_handler_wipe)=hangup-hook,s,1)
same => n,Return()

[apel_recent]
exten => s,1,Noop(Verific apel recent)
same => n,Set(apel_recent=${SHELL(/var/lib/asterisk/bin/apel_recent.php --action=get --number="${CALLERID(num)}")})
same => n,GotoIf($["${apel_recent}"=""]?ext-group,10,1)
same => n,Gosub(apel_manager,${apel_recent},1)

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
$minutes_store = 15;
$debug = 0;
ini_set('date.timezone', 'Europe/Chisinau');
ini_set('display_errors', 'Off');
error_reporting(E_ALL);

// +------------------------------+//
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

if ($o['action'] == 'store') {
    // ignoram apelurile fara raspuns
    if ($o['dst'] != '' && $o['disposition'] == 'ANSWERED') {
        if ($debug) debug("apel cu raspuns");
        exit;
    }
    if (preg_match("/^[0-9]{1,4}$/", $o['src'])) {
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
