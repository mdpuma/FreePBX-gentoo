#!/bin/bash -e
# sox master.wav out.ogg
# ffmpeg -f s16le -ar 8000 -i $IN -f ogg -codec:a libvorbis -qscale:a 5 -ac 2 $OUT -y 2>/dev/null

MONITOR_PATH=/var/spool/asterisk/monitor

FILES=`find $MONITOR_PATH -name \*.wav -type f -mtime +30 -print`

find $MONITOR_PATH -name \*.wav -type f -mtime +30 -print | while read input
	output="`echo $input | sed -E 's/wav/ogg/'`"
	recording_name="`basename $input`"
	echo "Processing $input"
	sox "$input" "$output"
	chown asterisk:asterisk "$output"
	mysql asteriskcdrdb -e "update cdr set recordingfile=REPLACE(recordingfile, 'wav','ogg') where recordingfile='$recording_name';"
	rm "$input" -v
done