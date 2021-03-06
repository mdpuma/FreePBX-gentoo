#!/bin/bash
# 
# Caution, this script will reinstall nginx, php-fpm, mariadb configuration and database
#

URL="https://raw.githubusercontent.com/mdpuma/FreePBX-gentoo/master/installer"
DBPASS="$(openssl rand -base64 32 | md5sum |cut -d ' ' -f1)"
DOMAIN='xxx'
EMAIL='xx@xx.xx'

# clean mysql config
CLEAN_MYSQL=1

# patch asterisk with pjproject bundled
PATCH_ASTERISK=0

# disable chan_sip.so module ?
DISABLE_CHANSIP=0

# install munin
USE_MUNIN=1

EMERGE_ARGS='-u --quiet-build --autounmask-continue --newuse'

function prestage() {
    wget --quiet $URL/etc-config/packages.use -O /etc/portage/package.use
    emerge --sync >/dev/null
    echo "emerge sync return code is $?"
    install_pkg "portage"
    install_pkg "cronie nginx php:5.6 mariadb pear PEAR-Console_Getopt sox mpg123 sudo exim app-crypt/gnupg dev-vcs/git logrotate app-editors/vim chrony"
    rc-update add cronie
    rc-update add chronyd
    eselect editor set vi
}

function install_munin() {
	if [ $USE_MUNIN -ne 1 ]; then
		exit
	fi
	
	install_pkg "net-analyzer/munin"
	
	munin-node-configure --shell | grep -E "^ln" | sh
	
	HOSTNAME=`hostname`
	IP=`curl -q http://2ip.ru -L`
	MASTER_IP="185.181.228.5"

	sed -E "/host_name/s/^(.*)$/host_name $HOSTNAME/" /etc/munin/munin-node.conf -i
	grep -i $MASTER_IP /etc/munin/munin-node.conf >/dev/null
	[[ $? -ne 0 ]] && echo "cidr_allow $MASTER_IP/32" >> /etc/munin/munin-node.conf
	rc-update add munin-node
	/etc/init.d/munin-node start
}

function postinstall_munin() {
	if [ $USE_MUNIN -ne 1 ]; then
		exit
	fi
	
	HOSTNAME=`hostname`
	IP=`curl -q http://2ip.ru -L`
	
	cat <<EOF
[client;$HOSTNAME]
        address $IP
EOF
}

function install_csf() {
    install_pkg "dev-perl/libwww-perl"
}

function configure_phpfpm() {
    if [ ! -d /etc/php/fpm-php5.6 ]; then
        echo "configure_phpfpm() php-fpm5.6 doesn't exists, check directory /etc/php/php-fpm5.6"
        exit 1
    fi
    eselect php set cli php5.6
    eselect php set fpm php5.6
    wget --quiet $URL/etc-config/php-fpm.openrc.init -O /etc/init.d/php-fpm
    wget --quiet $URL/etc-config/php-fpm.conf -O /etc/php/fpm-php5.6/php-fpm.conf
    wget --quiet $URL/etc-config/php.ini.txt -O /etc/php/fpm-php5.6/php.ini
    wget --quiet $URL/etc-config/php.ini.txt -O /etc/php/cli-php5.6/php.ini
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
	if [ $CLEAN_MYSQL -eq 1 ]; then
		rm /root/.my.cnf
	fi
	
    if [ -f /root/.my.cnf ]; then
        echo "mysqld is configured, skipping\n"
        return
    fi
    MYCNF_FILE=/etc/mysql/mariadb.d/50-distro-server.cnf
    sed -iE 's/^\(log-bin\)/#\1/' $MYCNF_FILE
    sed -iE 's/^tmpdir.*/tmpdir = \/tmpfs/' $MYCNF_FILE
    sed -iE 's/^innodb_buffer_pool_size.*/innodb_buffer_pool_size=128M/' $MYCNF_FILE
    
    mkdir /tmpfs
    chmod 777 /tmpfs
    
    cat << EOF >> /root/.my.cnf
[client]
password='$DBPASS'
EOF
    
    wget --quiet $URL/etc-config/mysql.openrc.init -O /etc/init.d/mysql
    chmod +x /etc/init.d/mysql
    
    # this will cause mysql init script to fix permissions for /var/run, probably which will help with emerge --config mariadb
    chown mysql:mysql /var/run/mysqld -Rv
    rm /var/lib/mysql/ -Rfv
    emerge --config mariadb
    /etc/init.d/mysql start
}

# do_letsencrypt sip.domain.com
function do_letsencrypt() {
    install_pkg certbot
    mkdir -p /var/www/html
    certbot certonly --email $2 --non-interactive --agree-tos --no-eff-email --webroot --webroot-path /var/www/html -d $1
    if [ $? -ne 0 ]; then
		echo "Can't get signed let's encrypt ssl certificate, error code $?"
		exit 1
	fi
	grep certbot /var/spool/cron/crontabs/root
	if [ $? -ne 0 ]; then
		cat << EOF >> /var/spool/cron/crontabs/root
MAILTO="$EMAIL"
0 0 1,15 * *  /usr/bin/certbot renew && /etc/init.d/nginx reload
EOF
	fi
}

# configure_nginx (pre letsencrypt)
function configure_nginx() {
    mkdir /etc/nginx/conf.d -p
    wget --quiet $URL/etc-config/nginx.conf -O /etc/nginx/nginx.conf
    wget --quiet $URL/etc-config/nginx-certbot.conf -O /etc/nginx/conf.d/freepbx.conf
    sed -iE "s/{{domain}}/$1/g" /etc/nginx/conf.d/freepbx.conf
    /etc/init.d/nginx restart
}

# configure_nginx (post letsencrypt)
function configure_nginx2() {
    wget --quiet $URL/etc-config/nginx-freepbx.conf -O /etc/nginx/conf.d/freepbx.conf
    [ ! -f /etc/nginx/dhparam.pem ] && openssl dhparam -out /etc/nginx/dhparam.pem 2048
    sed -iE "s/{{domain}}/$1/g" /etc/nginx/conf.d/freepbx.conf
    /etc/init.d/nginx restart
}

function do_preinstall_fixes() {
    ln -s /bin/ifconfig /sbin
    sed -i '/directories/s/(!)//' /etc/asterisk/asterisk.conf
    wget --quiet $URL/etc-config/logrotate-asterisk -O /etc/logrotate.d/asterisk
    rm /etc/freepbx.conf /etc/amportal.conf -v
    rm /etc/asterisk/* -rfv
    rm /var/www/html/* -rf
    mysql -e 'drop database asterisk'
    
    # required by freepbx 14
    cp /etc/pam.d/sudo /etc/pam.d/runuser
}

function do_install_asterisk() {
	version=13.23.1
	
	if [ $PATCH_ASTERISK -eq 1 ]; then
		cd /usr/portage/net-misc/asterisk
		
		if [ ! -f asterisk-$version.ebuild ]; then
			echo "ebuild file asterisk-$version.ebuild not found"
			exit 1
		fi
		sed -i 's/$(use_with pjproject)/--with-pjproject-bundled/' asterisk-$version.ebuild
		ebuild asterisk-$version.ebuild manifest
	fi
	install_pkg =net-misc/asterisk-$version
}

function do_install_unixodbc() {
	install_pkg unixODBC
	cd /tmp
	if [ ! -f "mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit.tar.gz" ]; then
		wget --quiet https://dev.mysql.com/get/Downloads/Connector-ODBC/5.3/mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit.tar.gz
	fi
	tar xf mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit.tar.gz
	cp mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit/lib/*.so /usr/lib64 -v
	rm /tmp/mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit/ -Rf
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
    [ ! -f "freepbx-14.0-latest.tgz" ] && wget --quiet http://mirror.freepbx.org/modules/packages/freepbx/freepbx-14.0-latest.tgz -O freepbx-14.0-latest.tgz
    tar xf freepbx-14.0-latest.tgz
    cd /var/www/freepbx
    /etc/init.d/asterisk restart
    ./install --dbpass=$DBPASS --no-interaction
    /etc/init.d/asterisk restart
    fwconsole reload
}

function configure_exim() {
    mkdir /var/log/exim && chown mail:mail /var/log/exim
    cd /etc/exim && cp exim.conf.dist exim.conf
    
    sed '/# primary_hostname =/s/# //' /etc/exim/exim.conf -i
    sed "/primary_hostname/s/=/= $DOMAIN/" /etc/exim/exim.conf -i
    
    grep '^root:' /etc/mail/aliases >/dev/null
    [[ $? -ne 0 ]] && echo "root: $EMAIL" >> /etc/mail/aliases
    
    grep '^fail2ban:' /etc/mail/aliases >/dev/null
    [[ $? -ne 0 ]] && echo "fail2ban: /dev/null" >> /etc/mail/aliases
    
    rc-update add exim
    /etc/init.d/exim restart
}
function configure_acpid() {
    install_pkg acpid
    rc-update add acpid
    /etc/init.d/acpid restart
}

function do_postinstall() {
	# restart php-fpm
	/etc/init.d/php-fpm restart
	/etc/init.d/cronie restart
	
	# disabling unnecessary modules
	modules="chan_sip.so cel_manager.so cel_odbc.so"
	for i in $modules; do
		grep $i /etc/asterisk/modules.conf
		
		[ $i = "chan_sip.so" ] && [ $DISABLE_CHANSIP -ne 1 ] && continue;
		
		if [ $? -eq 0 ]; then
			sed -i "/$i/d" /etc/asterisk/modules.conf
		fi
		echo "noload = $i" >> /etc/asterisk/modules.conf
	done
	
	fwconsole ma downloadinstall calendar queues
	fwconsole ma downloadinstall bulkhandler cel cidlookup asteriskinfo ringgroups timeconditions announcement 
	fwconsole reload
	/etc/init.d/asterisk restart
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
install_munin
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
postinstall_munin
