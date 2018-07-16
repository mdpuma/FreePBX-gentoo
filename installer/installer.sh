#!/bin/bash

URL="https://raw.githubusercontent.com/mdpuma/FreePBX-gentoo/master/installer"
DBPASS="$(openssl rand -base64 32 | md5sum |cut -d ' ' -f1)"
DOMAIN='xxx'
EMAIL='xx@xx.xx'

EMERGE_ARGS='-u --quiet-build --autounmask-continue --newuse'

function prestage() {
    wget $URL/etc-config/packages.use -O /etc/portage/package.use
    emerge --sync
    install_pkg "portage"
    install_pkg "vixie-cron nginx php:5.6 mariadb pear PEAR-Console_Getopt sox mpg123 sudo exim app-crypt/gnupg dev-vcs/git"
    rc-update add vixie-cron
}

function install_csf() {
    install_pkg "dev-perl/libwww-perl"
}

function configure_phpfpm() {
    if [ ! -d /etc/php/fpm-php5.6 ]; then
        echo "configure_phpfpm() php-fpm5.6 doesn't exists, check directory /etc/php/fpm-php5.6"
        exit 1
    fi
    eselect php set cli php5.6
    eselect php set fpm php5.6
    wget $URL/etc-config/php-fpm.openrc.init -O /etc/init.d/php-fpm
    wget $URL/etc-config/php-fpm.conf -O /etc/php/fpm-php5.6/php-fpm.conf
    wget $URL/etc-config/php.ini.txt -O /etc/php/fpm-php5.6/php.ini
    wget $URL/etc-config/php.ini.txt -O /etc/php/cli-php5.6/php.ini
    chmod +x /etc/init.d/php-fpm
}

function configure_autostart() {
    rc-update add local
    rc-update add nginx
    rc-update add mysql
    rc-update add php-fpm
    rc-update add asterisk
}

function configure_mysql() {
    if [ -f /root/.my.cnf ]; then
        echo "mysqld is configured, skipping\n"
        return
    fi
    sed -iE 's/^\(log-bin\)/#\1/' /etc/mysql/my.cnf
    sed -iE 's/^tmpdir.*/tmpdir = \/tmpfs/' /etc/mysql/my.cnf
    
    mkdir /tmpfs
    chmod 777 /tmpfs
    
    cat << EOF >> /root/.my.cnf
[client]
password='$DBPASS'
EOF
    
    wget $URL/etc-config/mysql.openrc.init -O /etc/init.d/mysql
    chmod +x /etc/init.d/mysql
    emerge --config mariadb
    /etc/init.d/mysql restart
}

# do_letsencrypt sip.domain.com
function do_letsencrypt() {
    install_pkg certbot
    mkdir -p /var/www/html
    certbot certonly --email $2 --non-interactive --agree-tos --no-eff-email --webroot --webroot-path /var/www/html -d $1
}

# configure_nginx (pre letsencrypt)
function configure_nginx() {
    mkdir /etc/nginx/conf.d -p
    wget $URL/etc-config/nginx.conf -O /etc/nginx/nginx.conf
    wget $URL/etc-config/nginx-certbot.conf -O /etc/nginx/conf.d/freepbx.conf
    sed -iE "s/{{domain}}/$1/g" /etc/nginx/conf.d/freepbx.conf
    /etc/init.d/nginx restart
}

# configure_nginx (post letsencrypt)
function configure_nginx2() {
    wget $URL/etc-config/nginx-freepbx.conf -O /etc/nginx/conf.d/freepbx.conf
    [ ! -f /etc/nginx/dhparam.pem ] && openssl dhparam -out /etc/nginx/dhparam.pem 2048
    sed -iE "s/{{domain}}/$1/g" /etc/nginx/conf.d/freepbx.conf
    /etc/init.d/nginx restart
}

function do_preinstall_fixes() {
    ln -s /bin/ifconfig /sbin
    sed -i '/directories/s/(!)//' /etc/asterisk/asterisk.conf
    wget $URL/etc-config/logrotate-asterisk -O /etc/logrotate.d/asterisk
    rm /etc/freepbx.conf /etc/amportal.conf -v
    rm /etc/asterisk/* -rfv
    rm /var/www/html/* -rf
    mysql -e 'drop database asterisk'
    
    # required by freepbx 14
    cp /etc/pam.d/sudo /etc/pam.d/runuser
}

function do_install_asterisk() {
	cd /usr/portage/net-misc/asterisk
	version=13.19.0-r1
	if [ ! -f asterisk-$version.ebuild ]; then
		echo "ebuild file asterisk-$version.ebuild not found"
		exit 1
	fi
	sed -i 's/$(use_with pjproject)/--with-pjproject-bundled/' asterisk-$version.ebuild
	ebuild asterisk-$version.ebuild manifest
	install_pkg =net-misc/asterisk-$version
}

function do_install_unixodbc() {
	install_pkg unixODBC
	cd /tmp
	wget https://dev.mysql.com/get/Downloads/Connector-ODBC/5.3/mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit.tar.gz
	tar xvf mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit.tar.gz
	cp mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit/lib/*.so /usr/lib64 -v
	cd -
	
	cat << EOF > /etc/unixODBC/odbcinst.ini
[MySQL]
Description = ODBC for MySQL
Driver=/usr/lib64/libmyodbc5a.so
Setup=/usr/lib64/libmyodbc5a.so
#Driver = /usr/lib/x86_64-linux-gnu/odbc/libmyodbc.so
#Setup = /usr/lib/x86_64-linux-gnu/odbc/libodbcmyS.so
FileUsage       = 1
;UsageCount = 2
EOF

	cat << EOF > /etc/unixODBC/odbc.ini
[MySQL-asteriskcdrdb]
Description=MySQL connection to 'asteriskcdrdb' database
driver=MySQL
server=localhost
database=asteriskcdrdb
Port=3306
Socket=/var/run/mysqld/mysqld.sock
option=3
Charset=utf8
EOF
}

function do_install_freepbx() {
    cd /var/www
    [ ! -f "freepbx-14.0-latest.tgz" ] && wget http://mirror.freepbx.org/modules/packages/freepbx/freepbx-14.0-latest.tgz -O freepbx-14.0-latest.tgz
    tar xf freepbx-14.0-latest.tgz
    cd /var/www/freepbx
    /etc/init.d/asterisk restart
    ./install --dbpass=$DBPASS --no-interaction
    /etc/init.d/asterisk restart
    fwconsole reload
}

function do_postinstall() {
	# restart php-fpm
	/etc/init.d/php-fpm restart
	/etc/init.d/vixie-cron restart
	
	cd /etc/asterisk
	
	# disabling unnecessary modules
	modules="chan_sip.so cel_manager.so cel_odbc.so"
	for i in $modules; do
		grep $i modules.conf
		if [ $? -eq 0 ]; then
			sed -i "/$i/d" modules.conf
		fi
		echo "noload = $i" >> modules.conf
	done
	
	fwconsole ma downloadinstall bulkhandler cel cidlookup asteriskinfo ringgroups timeconditions announcement 
	fwconsole reload
}

function configure_exim() {
    mkdir /var/log/exim && chown mail:mail /var/log/exim
    cd /etc/exim && cp exim.conf.dist exim.conf 
    rc-update add exim
    wget $URL/etc-config/exim.conf -O /etc/logrotate.d/asterisk
    /etc/init.d/exim restart
}
function configure_acpid() {
    install_pkg acpid
    rc-update add acpid
    /etc/init.d/acpid restart
}

function install_pkg() {
	cmd="emerge $EMERGE_ARGS $1"
	echo "Calling $cmd"
	$cmd
	ret_code=$?
	if [ $ret_code -ne 0 ]; then
		echo "Return code is $ret_code"
		exit 1
	fi
}

prestage
configure_nginx $DOMAIN
do_letsencrypt $DOMAIN $EMAIL
configure_nginx2 $DOMAIN
configure_phpfpm
configure_exim
configure_acpid
configure_mysql
do_install_asterisk
do_install_unixodbc
do_preinstall_fixes
do_install_freepbx
configure_autostart
do_postinstall
