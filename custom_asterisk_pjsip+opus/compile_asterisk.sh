#!/bin/bash -x

VERSION=13.19

# emerge required packages
emerge -avu libsrtp dev-libs/jansson unixODBC opus

rm -Rf asterisk-$VERSION*

# download asterisk
[ ! -f asterisk-13-current.tar.gz ] && wget http://downloads.asterisk.org/pub/telephony/asterisk/asterisk-13-current.tar.gz -O asterisk-13-current.tar.gz
tar xf asterisk-13-current.tar.gz

# patch with opus support
[ ! -d asterisk-opus ] && git clone https://github.com/mdpuma/asterisk-opus.git -b asterisk-13.7

cp -v ./asterisk-opus*/include/asterisk/* ./asterisk-$VERSION*/include/asterisk
cp -v ./asterisk-opus*/codecs/* ./asterisk-$VERSION*/codecs
cp -v ./asterisk-opus*/res/* ./asterisk-$VERSION*/res
cp -v ./asterisk-opus*/*.patch ./asterisk-$VERSION*/

cd asterisk-$VERSION*
patch -p1 -R < asterisk.patch
patch -p1 -R < enable_native_plc.patch
./bootstrap.sh

# configure & compile asterisk
./configure --with-pjproject-bundled 
make menuselect
make -j5

# install
make install
#make samples