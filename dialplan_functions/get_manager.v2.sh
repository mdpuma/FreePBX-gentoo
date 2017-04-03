#!/bin/bash

DID=$1
dir=`dirname $0`

# structure of manager_list.csv DID;DST
RESULT="`cat $dir/manager_list.csv | grep \"$DID;\" | cut -d';' -f2 | head -1`"
if [ "$RESULT" == "" ]; then
        echo -n 100
else
        echo -n $RESULT
fi