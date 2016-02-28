#!/bin/bash

# from-phone is dongle name
FROMPHONE=$1
TOPHONE=$2
#EMAIL="admin@test.com"

read MESSAGE 
MESSAGE=`echo $MESSAGE | base64 -d`

echo -e "$MESSAGE\n\n*** Received from $FROMPHONE, at phone number $TOPHONE" | mailx -s "Incoming SMS [$FROMPHONE]" $EMAIL 
