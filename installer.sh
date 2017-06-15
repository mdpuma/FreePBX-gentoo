#!/bin/bash

URL="https://raw.githubusercontent.com/mdpuma/FreePBX-gentoo/master"
DBPASS='0f4df76e364aadcdfb0c59a91d786699'

function install_csf() {
    emerge -avu --autounmask-continue dev-perl/libwww-perl
}

function configure_phpfpm() {
    if [ ! -d /etc/php/fpm-php5.6 ]; then
        echo "configure_phpfpm() php-fpm5.6 doesn't exists, check directory /etc/php/fpm-php5.6"
        exit 1
    fi
    wget $URL/etc-config/php-fpm.openrc.init -O /etc/init.d/php-fpm
    wget $URL/etc-config/mysql.openrc.init -O /etc/init.d/mysql
    
    wget $URL/etc-config/php-fpm.conf -O /etc/php/fpm-php5.6/php-fpm.conf
    wget $URL/etc-config/php.ini.txt -O /etc/php/fpm-php5.6/php.ini
    wget $URL/etc-config/php.ini.txt -O /etc/php/cli-php5.6/php.ini
    chmod +x /etc/init.d/php-fpm
    chmod +x /etc/init.d/mysql
}

function configure_autostart() {
    rc-update add local
    rc-update add nginx
    rc-update add mysql
    rc-update add php-fpm
    rc-update add asterisk
}

function configure_mysql() {
    sed -iE 's/^\(log-bin\)/#\1/' /etc/mysql/my.cnf
    sed -iE 's/^tmpdir.*/tmpdir = \/tmpfs/' /etc/mysql/my.cnf
    
    mkdir /tmpfs
    chmod 777 /tmpfs
    
    cat << EOF >> /root/.my.cnf
[client]
password='$DBPASS'
EOF
    
    emerge --config mariadb
    /etc/init.d/mysql restart
}

# do_letsencrypt sip.domain.com
function do_letsencrypt() {
    emerge -avu --autounmask-continue certbot
    certbot certonly --webroot --webroot-path /var/www/html -d $1
}

# configure_nginx sip.domain.com
function configure_nginx() {
    mkdir /etc/nginx/conf.d -p
    wget $URL/etc-config/nginx.conf -O /etc/nginx/nginx.conf
    wget $URL/etc-config/nginx-freepbx.conf -O /etc/nginx/conf.d/freepbx.conf
    openssl dhparam -out /etc/nginx/dhparam.pem 2048
    sed -iE "s/{{domain}}/$1/g" /etc/nginx/conf.d/freepbx.conf
}

function do_preinstall_fixes() {
    ln -s /bin/ifconfig /sbin
    sed -i '/directories/s/(!)//' /etc/asterisk/asterisk.conf
}

function do_install_freepbx() {
    cd /var/www && wget http://mirror.freepbx.org/modules/packages/freepbx/freepbx-13.0-latest.tgz -O freepbx-13.0-latest.tgz && tar xf freepbx-13.0-latest.tgz
    cd /var/www/freepbx
    /etc/init.d/asterisk restart
    ./install --dbpass=$DBPASS
}

function configure_exim() {
    mkdir /var/log/exim && chown mail:mail /var/log/exim
    cd /etc/exim && cp exim.conf.dist exim.conf 
    rc-update add exim && /etc/init.d/exim restart
    echo "add local_interfaces = 127.0.0.1 in to /etc/exim.conf to disable listening on public ip address"
}



wget $URL/etc-config/packages.use -O /etc/portage/package.use

emerge -avu --autounmask-continue nginx php:5.6 mariadb pear PEAR-Console_Getopt sox mpg123 sudo asterisk exim =app-crypt/gnupg-1.4.21

configure_nginx
configure_phpfpm
configure_autostart
configure_exim
do_preinstall_fixes