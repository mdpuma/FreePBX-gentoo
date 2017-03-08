#!/bin/bash

DID=$1
echo $DID | grep 07832005 >/dev/null 2>&1
if [ $? -ne 0 ]; then
        echo -n 100
else
        echo -n $DID | sed 's/07832005/10/'
fi
