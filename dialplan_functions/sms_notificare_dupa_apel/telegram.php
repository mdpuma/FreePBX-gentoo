#!/usr/bin/env php
<?php
#This script helps integrate Nagios instances
#with telegrams chats or channels.

#Parse arguments
ini_set('display_errors', 'Off');

$contactName=$argv[1];
$messageText='';
$f = fopen( 'php://stdin', 'r' );
while( $line = fgets( $f ) ) {
    $messageText.='\n'.trim($line);
}
fclose( $f );

$contactName = str_replace(' ', '_', $contactName);
//$contactName = $contactName.'#1';


system("sudo /usr/local/bin/telegram-cli \
    --rsa-key \"/usr/local/bin/tg-server.pub\" \
    --wait-dialog-list \
    --exec \"msg $contactName '$messageText'\" \
    --disable-link-preview \
    --disable-colors --disable-readline \
    --logname \"/var/log/telegram/telegram.log\" >> /var/log/telegram/telegram.log");

exit;