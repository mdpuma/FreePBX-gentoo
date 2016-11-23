#!/bin/bash

emerge -avu libsrtp dev-libs/jansson

wget http://downloads.asterisk.org/pub/telephony/asterisk/asterisk-13-current.tar.gz -O asterisk-13-current.tar.gz
tar xf asterisk-13-current.tar.gz
cd asterisk-13*

./configure --with-pjproject-bundled 
make menuselect
make -j5
make install
make samples

# download opus codec & transcoder
