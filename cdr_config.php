<?php
$src = '/etc/asterisk/res_odbc_additional.conf';
$dst = '/etc/asterisk/cdr_mysql.conf';

$array = parse_ini_file($src, true);

$array2 = $array['asteriskcdrdb'];
foreach ($array2 as $i => $j) {
    $array2[$i] = substr($j, 1);
}

$template = <<<EOF

[global]
hostname=localhost
dbname={$array2['database']}
table=cdr
password={$array2['password']} 
user={$array2['username']}
;port=3306
;sock=/tmp/mysql.sock
; By default CDRs are logged in the system's time zone
;cdrzone=UTC               ; log CDRs with UTC
;usegmtime=yes ;log date/time in GMT.  Default is "no"
;cdrzone=America/New_York  ; or use a specific time zone
;
; If your system's locale differs from mysql database character set,
; cdr_mysql can damage non-latin characters in CDR variables. Use this
; option to protect your data.
;charset=koi8r
;
; Older versions of cdr_mysql set the calldate field to whenever the
; record was posted, rather than the start date of the call.  This flag
; reverts to the old (incorrect) behavior.  Note that you'll also need
; to comment out the "start=calldate" alias, below, to use this.
;compat=no
;
; ssl connections (optional)
;ssl_ca=<path to CA cert>
;ssl_cert=<path to cert>
;ssl_key=<path to keyfile>
;
; You may also configure the field names used in the CDR table.
;
[columns]
;static "<value>" => <column>
;alias <cdrvar> => <column>
alias start => calldate
;alias clid => <a_field_not_named_clid>
;alias src => <a_field_not_named_src>
;alias dst => <a_field_not_named_dst>
;alias dcontext => <a_field_not_named_dcontext>
;alias channel => <a_field_not_named_channel>
;alias dstchannel => <a_field_not_named_dstchannel>
;alias lastapp => <a_field_not_named_lastapp>
;alias lastdata => <a_field_not_named_lastdata>
;alias duration => <a_field_not_named_duration>
;alias billsec => <a_field_not_named_billsec>
;alias disposition => <a_field_not_named_disposition>
;alias amaflags => <a_field_not_named_amaflags>
;alias accountcode => <a_field_not_named_accountcode>
;alias userfield => <a_field_not_named_userfield>
;alias uniqueid => <a_field_not_named_uniqueid>
EOF;


$fp = fopen($dst, 'w');
if (!fwrite($fp, $template)) {
    echo "cant write file " . $dst . "\n";
    exit;
}

echo "now make sure that cdr_mysql are loaded /etc/asterisk/modules.conf\n";

