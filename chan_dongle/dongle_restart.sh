#!/bin/bash

asterisk -rx 'dongle show devices' | grep dc_ | cut -d ' ' -f1 | while read i; do
	# graceful restart dongle
	asterisk -rx "dongle restart when convenient $i"
	# switch to 2G/EDGE dongle
#	asterisk -rx "dongle cmd $i AT^SYSCFG=13,0,3FFFFFFF,0,3"
	sleep 10
done