#!/bin/bash

/etc/init.d/asterisk restart
if [ $? -ne 0 ]; then
        killall asterisk -9 -v
        /etc/init.d/asterisk restart
fi 
