#!/bin/bash

SU="su asterisk -s"

chattr -i /var/spool/cron/crontabs/asterisk

$SU /usr/sbin/fwconsole ma upgradeall
$SU /usr/sbin/fwconsole reload

[ -f /etc/init.d/vixie-cron ] && chattr +i /var/spool/cron/crontabs/asterisk 
