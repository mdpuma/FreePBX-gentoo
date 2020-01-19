#!/bin/bash

modules="cdr_mysql.so
chan_iax2.so
chan_mgcp.so
chan_phone.so
chan_skinny.so
chan_unistim.so
codec_adpcm.so
codec_g722.so
codec_g726.so
codec_gsm.so
codec_lpc10.so
format_g719.so
format_g723.so
format_g726.so
format_g729.so
format_gsm.so
format_h263.so
format_h264.so
format_jpeg.so
format_siren14.so
format_siren7.so
format_vox.so
pbx_ael.so
pbx_dundi.so
pbx_loopback.so
pbx_realtime.so
res_realtime.so
res_rtp_multicast.so
res_srtp.so"

for i in $modules; do
	grep $i /etc/asterisk/modules.conf
	if [ $? -eq 0 ]; then
		sed -i "/$i/d" /etc/asterisk/modules.conf
	fi
	echo "noload = $i" >> /etc/asterisk/modules.conf
done