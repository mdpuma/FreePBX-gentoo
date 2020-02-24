#!/bin/bash
# 
# First of all do not forget to configure static ip https://netplan.io/examples#using-dhcp-and-static-addressing
#
# Caution, this script will reinstall nginx, php-fpm, mariadb configuration and database
#

URL="https://raw.githubusercontent.com/mdpuma/FreePBX-gentoo/master/installer_ubuntu"
DBPASS="$(openssl rand -base64 32 | md5sum |cut -d ' ' -f1)"
DOMAIN='xxx'
EMAIL='xx@xx.xx'

# clean mysql config
CLEAN_MYSQL=1

# disable chan_sip.so module ?
DISABLE_CHANSIP=0

# install munin
USE_MUNIN=1

INSTALL_ARGS='-y'

function prestage() {
	sed -ir 's/#?PermitRootLog.+/PermitRootLogin yes/' /etc/ssh/sshd_config
	systemctl restart sshd
	
	apt-get install -y software-properties-common
	add-apt-repository ppa:ondrej/php < /dev/null
	apt-get update && apt-get upgrade -y
	
	mkdir /run/php -p 
	
	install_pkg "vim curl wget net-tools openssh-server nginx mysql-server mysql-client \
	curl sox mpg123 sqlite3 git uuid libodbc1 unixodbc unixodbc-bin \
	asterisk asterisk-core-sounds-en-wav asterisk-core-sounds-en-g722 \
	asterisk-dahdi asterisk-flite asterisk-modules asterisk-mp3 asterisk-mysql \
	asterisk-moh-opsound-g722 asterisk-moh-opsound-wav asterisk-opus \
	asterisk-voicemail \
	php5.6 php5.6-cgi php5.6-cli php5.6-curl php5.6-fpm php5.6-gd php5.6-mbstring \
	php5.6-mysql php5.6-odbc php5.6-xml php5.6-bcmath php-pear libicu-dev gcc \
	g++ make exim4"
  
	curl -sL https://deb.nodesource.com/setup_10.x | bash -
	install_pkg nodejs

	chown asterisk. /var/run/asterisk
	chown -R asterisk. /etc/asterisk
	chown -R asterisk. /var/{lib,log,spool}/asterisk
	chown -R asterisk. /usr/lib/asterisk
	rm -rf /var/www/html
}

function install_munin() {
	if [ $USE_MUNIN -ne 1 ]; then
		exit
	fi
	
	install_pkg munin-node
	
# 	munin-node-configure --shell | grep -E "^ln" | sh
	
	HOSTNAME=`hostname`
	IP=`curl -q http://2ip.ru -L`
	MASTER_IP="185.181.228.5"

	sed -E "/host_name/s/^(.*)$/host_name $HOSTNAME/" /etc/munin/munin-node.conf -i
	grep -i $MASTER_IP /etc/munin/munin-node.conf >/dev/null
	[[ $? -ne 0 ]] && echo "cidr_allow $MASTER_IP/32" >> /etc/munin/munin-node.conf
	systemctl enable munin-node
	systemctl restart munin-node
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

function configure_phpfpm() {
	DIR=/etc/php/5.6/fpm
    if [ ! -d $DIR ]; then
        echo "configure_phpfpm() php-fpm5.6 doesn't exists, check directory $DIR"
        exit 1
    fi
    wget --quiet $URL/etc-config/php-fpm.conf -O $DIR/php-fpm.conf
    wget --quiet $URL/etc-config/php.ini.txt -O $DIR/php.ini
    systemctl restart php5.6-fpm
}

function configure_autostart() {
	systemctl enable nginx
	systemctl enable mariadb
	systemctl enable asterisk
	systemctl enable php5.6-fpm
}

function configure_mysql() {
	if [ $CLEAN_MYSQL -eq 1 ]; then
		rm /root/.my.cnf
		rm /var/lib/mysql/* -rf
		mysqld --initialize-insecure
		systemctl restart mysql
	fi
	
	wget --quiet $URL/etc-config/logrotate-mysql -O /etc/logrotate.d/mysql-server
	
#     MYCNF_FILE=/etc/mysql/mariadb.d/50-distro-server.cnf
#     sed -iE 's/^\(log-bin\)/#\1/' $MYCNF_FILE
#     sed -iE 's/^tmpdir.*/tmpdir = \/tmpfs/' $MYCNF_FILE
#     sed -iE 's/^innodb_buffer_pool_size.*/innodb_buffer_pool_size=128M/' $MYCNF_FILE
#     
#     mkdir /tmpfs
#     chmod 777 /tmpfs

    cat << EOF >> /root/.my.cnf
[client]
password='$DBPASS'
EOF
	echo "USE mysql; UPDATE user SET plugin='mysql_native_password', authentication_string=PASSWORD('$DBPASS') WHERE user='root'; FLUSH PRIVILEGES" | mysql --skip-password
	systemctl enable mysql
	systemctl restart mysql
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
    systemctl restart nginx
}

# configure_nginx (post letsencrypt)
function configure_nginx2() {
    wget --quiet $URL/etc-config/nginx-freepbx.conf -O /etc/nginx/conf.d/freepbx.conf
    [ ! -f /etc/nginx/dhparam.pem ] && openssl dhparam -out /etc/nginx/dhparam.pem 2048
    sed -iE "s/{{domain}}/$1/g" /etc/nginx/conf.d/freepbx.conf
    systemctl restart nginx
}

function do_preinstall_fixes() {
	rm -rf /etc/asterisk/ext* /etc/asterisk/sip* /etc/asterisk/pj* /etc/asterisk/iax* /etc/asterisk/manager*
	sed -i 's/.!.//' /etc/asterisk/asterisk.conf
	
	sed -i 's/ each(/ @each(/' /usr/share/php/Console/Getopt.php
	
    
    #wget --quiet $URL/etc-config/logrotate-asterisk -O /etc/logrotate.d/asterisk
    rm /etc/freepbx.conf /etc/amportal.conf -v
    rm /etc/asterisk/* -rfv
    rm /var/www/html/* -rf
    mysql -e 'drop database asterisk'
    
#     # required by freepbx 14
#     cp /etc/pam.d/sudo /etc/pam.d/runuser
	wget --quiet $URL/etc-config/asterisk.conf -O /etc/asterisk/asterisk.conf
}

function do_install_unixodbc() {
	mkdir -p /usr/lib/odbc
	curl -s https://cdn.mysql.com//Downloads/Connector-ODBC/5.3/mysql-connector-odbc-5.3.14-linux-ubuntu18.04-x86-64bit.tar.gz | \
	tar -C /usr/lib/odbc --strip-components=2 --wildcards -zxvf - */lib/*so
	
	cat > /etc/odbc.ini << EOF
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
cat > /etc/odbcinst.ini << EOF
[MySQL]
Description=ODBC for MySQL
Driver=/usr/lib/odbc/libmyodbc5w.so
Setup=/usr/lib/odbc/libodbcmy5S.so
FileUsage=1
EOF
}

function do_install_freepbx() {
    cd /var/www
    [ ! -f "freepbx-14.0-latest.tgz" ] && wget --quiet http://mirror.freepbx.org/modules/packages/freepbx/freepbx-14.0-latest.tgz -O freepbx-14.0-latest.tgz
    tar xf freepbx-14.0-latest.tgz
    cd /var/www/freepbx
    systemctl restart asterisk
    ./install --dbpass=$DBPASS --no-interaction
    systemctl restart asterisk
    fwconsole reload
}

function configure_exim() {
	wget --quiet $URL/etc-config/update-exim4.conf.conf -O /etc/exim4/update-exim4.conf.conf
	echo $DOMAIN > /etc/mailname
	sed -iE "s/{{ domain }}/$DOMAIN/g" /etc/exim4/update-exim4.conf.conf
	
	update-exim4.conf
	
    
    grep '^root:' /etc/aliases >/dev/null
    [[ $? -ne 0 ]] && echo "root: $EMAIL" >> /etc/aliases
    
    grep '^fail2ban:' /etc/aliases >/dev/null
    [[ $? -ne 0 ]] && echo "fail2ban: /dev/null" >> /etc/aliases
    
    systemctl enable exim4
    systemctl restart exim4
}
function configure_acpid() {
    install_pkg acpid
    systemctl enable acpid
    systemctl restart acpid
}

function do_postinstall() {
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
	
	# copy moh sounds
	cp -Rfp /usr/share/asterisk/moh/* /var/lib/asterisk/moh/
	chown asterisk:asterisk /var/lib/asterisk/moh/ -Rf
	
	systemctl restart asterisk
}

function configure_firewall() {
	ufw allow 5060/udp
	ufw allow 10000:20000/udp
	ufw allow 80/tcp
	ufw allow 443/tcp
	ufw prepend allow from 185.181.228.5
	ufw prepend allow from 185.181.228.28
	ufw prepend allow from 89.28.42.226
	echo 'y' | ufw enable
}

function install_pkg() {
	cmd="apt-get install $1 $INSTALL_ARGS"
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
do_install_unixodbc
do_preinstall_fixes
do_install_freepbx
configure_autostart
do_postinstall
configure_firewall
postinstall_munin
