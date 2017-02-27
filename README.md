# FreePBX-gentoo

1. Configure /etc/portage/package.use

net-misc/asterisk http mysql
dev-lang/php mysql opcache fpm pdo curl gd mbstring gettext
media-sound/sox flac opus ogg wavpack
media-video/ffmpeg vorbis

2. Install asterisk & required packages

# emerge -avu nginx php:5.6 mariadb pear PEAR-Console_Getopt sox mpg123 sudo asterisk
    
3. Install additional packages for chan_dongle
# emerge -avu picocom

4. Configure nginx & php-fpm

# run:
echo php-fpm -y /etc/php/fpm-php5.6/php-fpm.conf >> /etc/local.d/php.start; chmod +x /etc/local.d/php.start; 

rc-update add local
rc-update add nginx
rc-update add mysql

# replace from /etc/mysql/my.cnf
#log-bin
skip-networking
tmpdir = /tmpfs
slave_load_tmpdir = /tmpfs

# mkdir /tmpfs
mkdir /tmpfs; chmod 777 /tmpfs

5. Generate dh-param for ssl_certificate

openssl dhparam -out /etc/nginx/dhparam.pem 2048


6. Download and install FreePBX

cd /var/www
wget http://mirror.freepbx.org/modules/packages/freepbx/freepbx-13.0-latest.tgz -O freepbx-13.0-latest.tgz
tar xf freepbx-13.0-latest.tgz
