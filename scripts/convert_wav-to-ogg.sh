#!/bin/bash
set -euo pipefail
IFS=$'\n\t'

MONITOR_PATH="/var/spool/asterisk/monitor"

# 1. Delete broken WAV files of exactly 44 bytes
echo "🧹 Cleaning up .wav files of size 44 bytes..."
find "$MONITOR_PATH" -type f -name "*.wav" -size 44c -print -delete

# 2. Convert remaining old wav files
echo "🎵 Processing .wav files older than 7 days..."
find "$MONITOR_PATH" -type f -name "*.wav" -mtime +7 | while read -r input; do
    output="${input%.wav}.ogg"
    recording_name=$(basename "$input")

    echo "Processing: $input -> $output"

    if sox "$input" "$output"; then
        # preserve timestamps
        touch --reference="$input" "$output"

        # fix ownership
        chown asterisk:asterisk "$output"

        # update DB (replace only extension)
        mysql asteriskcdrdb -e \
          "UPDATE cdr 
           SET recordingfile=CONCAT(SUBSTRING_INDEX(recordingfile, '.', 1), '.ogg') 
           WHERE recordingfile='$recording_name';"

        rm -v "$input"
    else
        echo "❌ Failed to convert $input" >&2
    fi
done

