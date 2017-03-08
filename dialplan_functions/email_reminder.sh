#!/bin/bash

# 1 - action; 2 - callerid; 3 - did; 4 - disposition
FILEDAT=/tmp/apeluri_pierdute.csv
EMAIL=admin@iphost.md

case "$1" in
    store)
        if [ ! -f "$FILEDAT" ]; then
            echo '"ora-data";"numar client";"numar apelat";"dispozitia"' | tee -a $FILEDAT
        fi
        echo "\"`date`\";\"$2\";\"$3\";\"$4\"" | tee -a $FILEDAT
        ;;
    sendemail)
        if [ -f "$FILEDAT" ]; then
            echo 'notificare apeluri pierdute' | mutt -a $FILEDAT -s "PBX: apeluri pierdute" -- $EMAIL
        fi
        rm $FILEDAT
        ;;
esac