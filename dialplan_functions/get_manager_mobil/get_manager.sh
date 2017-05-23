#!/bin/bash

ID=$1
dir=`dirname $0`

# structure of manager_list.csv ID,name,mobil
RESULT="`cat $dir/manager_list.csv | grep -E \"^$ID,\" | cut -d',' -f3 | head -1`"
if [ "$RESULT" == "" ]; then
        echo -n 079837770
else
        echo -n $RESULT
fi