#!/bin/bash

# emerge required packages
emerge -avu libsrtp dev-libs/jansson unixODBC opus

# download asterisk
wget http://downloads.asterisk.org/pub/telephony/asterisk/asterisk-13-current.tar.gz -O asterisk-13-current.tar.gz
tar xf asterisk-13-current.tar.gz

# patch with opus support
git clone https://github.com/mdpuma/asterisk-opus.git -b asterisk-13.7
cd asterisk-13.7

cp -v ./asterisk-opus*/include/asterisk/* ./asterisk-13*/include/asterisk
cp -v ./asterisk-opus*/codecs/* ./asterisk-13*/codecs
cp -v ./asterisk-opus*/res/* ./asterisk-13*/res
cp -v ./asterisk-opus*/*.patch ./asterisk-13*/
cd asterisk-13*
patch -p1 < asterisk.patch
patch -p1 < enable_native_plc.patch
./bootstrap.sh

# configure & compile asterisk
./configure --with-pjproject-bundled 
make menuselect
make -j5

# install
make install
make samples