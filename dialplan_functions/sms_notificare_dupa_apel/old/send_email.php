#!/usr/bin/env php
<?php

// System(/var/lib/asterisk/bin/send_email.php --src="${CDR(src)}" --srcname="${CALLERID(name)}" --did="${CDR(dnid)}" --dst="${CDR(dstchannel)}" --disposition="${CDR(disposition)}" --context="${CDR(dcontext)}")

$o = getopt('', array('src:','srcname:','did:','dst:','disposition:','context:'));

$EMAIL = 'admin@iphost.md';

ini_set('date.timezone', 'Europe/Chisinau');
//+------------------------------+//

//ignoram apelurile fara raspuns
if($o['dst'] != '' && $o['disposition'] == 'ANSWERED') {
        die("apel cu raspuns");
}

if(!isset($o['srcname']) || empty($o['srcname'])) {
        $o['srcname']=$o['src'];
}

if(preg_match("/^[0-9]{1,3}$/", $o['src'])) {
        die("apel de iesire");
}

$MSG = <<<EOF
Salut!
Apel pierdut de la ${o['src']} (${o['srcname']}) 
EOF;

//+------------------------------+//

mail($EMAIL, 'Apel pierdut de la '.$o['src'], $MSG);

?>
