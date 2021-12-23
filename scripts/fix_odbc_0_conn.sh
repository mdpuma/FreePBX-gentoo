#!/bin/bash

CONN=$(asterisk -rx 'odbc show all' | grep 'Number of active' | awk '{ print $5 }')

if [ $CONN -eq 0 ]; then
	asterisk -rx 'core reload'
	echo "Reloading asterisk - zero odbc connections"
fi
