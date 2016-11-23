#!/bin/bash -e
# sox master.wav out.ogg
# ffmpeg -f s16le -ar 8000 -i $IN -f ogg -codec:a libvorbis -qscale:a 5 -ac 2 $OUT -y 2>/dev/null

MONITOR_PATH=/var/spool/asterisk/monitor

#FILES=`find $MONITOR_PATH -name \*.wav -type f -print`
FILES=`find $MONITOR_PATH -name \*.wav -type f -mtime +30 -print`

for input in $FILES; do
	output="`echo $input | sed -E 's/wav/ogg/'`"
	recording_name="`basename $input`"
	echo "Processing $input"
	rm $input -v
	sox $input $output
	chown asterisk:asterisk $output
	mysql asteriskcdrdb -e "update cdr set recordingfile=REPLACE(recordingfile, 'wav','ogg') where recordingfile='$recording_name';"
done

# mysql asteriskcdrdb -e "update cdr set recordingfile=REPLACE(recordingfile, 'wav','ogg');"
# mysql asteriskcdrdb -e "update cdr set recordingfile=REPLACE(recordingfile, 'wav','ogg') where calldate < NOW() - INTERVAL 30 DAY;"