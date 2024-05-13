#!/bin/bash
# 
# First of all do not forget to configure static ip https://netplan.io/examples#using-dhcp-and-static-addressing
#
# Caution, this script will reinstall nginx, php-fpm, mariadb configuration and database
#

URL="https://raw.githubusercontent.com/mdpuma/FreePBX-gentoo/master"
DBPASS="$(openssl rand -base64 32 | md5sum |cut -d ' ' -f1)"
DOMAIN=''
EMAIL=''

# clean mysql config
CLEAN_MYSQL=1

# disable chan_sip.so module ?
DISABLE_CHANSIP=0

# install munin
USE_MUNIN=0

# install node_exporter
USE_NODE_EXPORTER=1

INSTALL_ARGS='-y'

function prestage() {
	sed -ir 's/#?PermitRootLog.+/PermitRootLogin yes/' /etc/ssh/sshd_config
	systemctl restart sshd
	
	systemctl disable apparmor
	systemctl stop apparmor
	
	apt-get install -y software-properties-common
	add-apt-repository ppa:ondrej/php < /dev/null
	apt-get update && apt-get upgrade -y
	
	mkdir /run/php -p 
	
	# unixodbc-bin not exists on ubuntu 20.04
	# asterisk-dahdi, asterisk-flite, asterisk-mp3 unecessary
	install_pkg "vim curl wget net-tools openssh-server nginx mariadb-server mariadb-client \
	curl sox mpg123 sqlite3 git uuid libodbc1 unixodbc \
	asterisk asterisk-core-sounds-en-wav asterisk-modules \
	asterisk-mysql asterisk-moh-opsound-wav \
	php7.4 php7.4-cgi php7.4-cli php7.4-curl php7.4-fpm php7.4-gd php7.4-mbstring \
	php7.4-mysql php7.4-odbc php7.4-xml php7.4-bcmath php-pear libicu-dev gcc \
	g++ make pkg-config exim4 sngrep"
  
	sudo apt-get install -y ca-certificates gnupg
    sudo mkdir -p /etc/apt/keyrings
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | sudo gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
    
    echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_18.x nodistro main" | sudo tee /etc/apt/sources.list.d/nodesource.list
    
    apt-get update 
    apt-get install nodejs -y

	chown asterisk. /var/run/asterisk
	chown -R asterisk. /etc/asterisk
	chown -R asterisk. /var/{lib,log,spool}/asterisk
	chown -R asterisk. /usr/lib/asterisk
	rm -rf /var/www/html
}

function install_munin() {
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
	HOSTNAME=`hostname`
	IP=`curl -q http://2ip.ru -L`
	
	cat <<EOF
[client;$HOSTNAME]
        address $IP
EOF
}

function configure_phpfpm() {
	DIR=/etc/php/7.4
    if [ ! -d $DIR ]; then
        echo "configure_phpfpm() directory $DIR doesn't exists!"
        exit 1
    fi
    wget --quiet $URL/installer_ubuntu/etc-config/php-fpm.conf -O $DIR/fpm/php-fpm.conf
    wget --quiet $URL/installer_ubuntu/etc-config/php.ini.txt -O $DIR/fpm/php.ini
    wget --quiet $URL/installer_ubuntu/etc-config/php.ini.txt -O $DIR/cli/php.ini
    systemctl restart php7.4-fpm
}

function configure_autostart() {
	systemctl enable nginx
	systemctl enable mariadb
	systemctl enable asterisk
	systemctl enable php7.4-fpm
}

function configure_mysql() {
	if [ $CLEAN_MYSQL -eq 1 ]; then
		rm /root/.my.cnf
		rm /var/lib/mysql/* -rf
# 		mysqld --initialize-insecure
		mysql_install_db
		chown mysql:mysql /var/lib/mysql/ -Rf
		systemctl restart mariadb
	fi
	
	wget --quiet $URL/installer_ubuntu/etc-config/logrotate-mysql -O /etc/logrotate.d/mysql-server
	
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
	systemctl restart mariadb
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
0 * * * *     /root/fix_odbc_0_conn.sh >/dev/null 2>&1
EOF

	fi
    chmod 600 /var/spool/cron/crontabs/root
}

# configure_nginx (pre letsencrypt)
function configure_nginx() {
    mkdir /etc/nginx/conf.d -p
    wget --quiet $URL/installer_ubuntu/etc-config/nginx.conf -O /etc/nginx/nginx.conf
    wget --quiet $URL/installer_ubuntu/etc-config/nginx-certbot.conf -O /etc/nginx/conf.d/freepbx.conf
    sed -iE "s/{{domain}}/$1/g" /etc/nginx/conf.d/freepbx.conf
    systemctl restart nginx
}

# configure_nginx (post letsencrypt)
function configure_nginx2() {
    wget --quiet $URL/installer_ubuntu/etc-config/nginx-freepbx.conf -O /etc/nginx/conf.d/freepbx.conf
    [ ! -f /etc/nginx/dhparam.pem ] && openssl dhparam -out /etc/nginx/dhparam.pem 2048
    sed -iE "s/{{domain}}/$1/g" /etc/nginx/conf.d/freepbx.conf
    systemctl restart nginx
}

function do_preinstall_fixes() {
# 	rm -rf /etc/asterisk/ext* /etc/asterisk/sip* /etc/asterisk/pj* /etc/asterisk/iax* /etc/asterisk/manager*
# 	sed -i 's/.!.//' /etc/asterisk/asterisk.conf
	
	sed -i 's/ each(/ @each(/' /usr/share/php/Console/Getopt.php
	
    
    #wget --quiet $URL/installer_ubuntu/etc-config/logrotate-asterisk -O /etc/logrotate.d/asterisk
    rm /etc/freepbx.conf /etc/amportal.conf -v
#    rm /etc/asterisk/* -rfv
    rm /var/www/html/* -rf
    mysql -e 'drop database asterisk'
    
#     # required by freepbx 14
#     cp /etc/pam.d/sudo /etc/pam.d/runuser
	wget --quiet $URL/installer_ubuntu/etc-config/asterisk.ubuntu2204.conf -O /etc/asterisk/asterisk.conf
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
    [ ! -f "freepbx-16.0-latest.tgz" ] && wget --quiet http://mirror.freepbx.org/modules/packages/freepbx/7.4/freepbx-16.0-latest.tgz -O freepbx-16.0-latest.tgz
    tar xf freepbx-16.0-latest.tgz
    cd /var/www/freepbx
    systemctl restart asterisk
    ./install --dbpass=$DBPASS --no-interaction
    systemctl restart asterisk
    fwconsole reload
}

function configure_exim() {
	wget --quiet $URL/installer_ubuntu/etc-config/update-exim4.conf.conf -O /etc/exim4/update-exim4.conf.conf
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
	
	fwconsole ma downloadinstall calendar queues recordings
	fwconsole ma downloadinstall bulkhandler cel cidlookup asteriskinfo ringgroups timeconditions announcement 
	fwconsole reload
	
	# copy moh sounds
	cp -Rfp /usr/share/asterisk/moh/* /var/lib/asterisk/moh/
	chown asterisk:asterisk /var/lib/asterisk/moh/ -Rf
	
	# fix sounds directory linking
	cp -Rfp /var/lib/asterisk/sounds/* /usr/share/asterisk/sounds/
	rm -Rf /var/lib/asterisk/sounds
	ln -s /usr/share/asterisk/sounds /var/lib/asterisk/sounds
	
	# fix soundLang module
	sed -i '/Uninstalling %s/s/sprintf(_/_(sprintf/' /var/www/html/admin/modules/soundlang/Console/Soundlang.class.php
	
	# reinstall sounds
	rm -Rf /var/lib/asterisk/sounds/* 
	fwconsole sounds --uninstall=en
	fwconsole sounds --install=en
	
	systemctl restart asterisk
	
	# fix odbc conn 0
	wget --quiet $URL/installer_ubuntu/scripts/fix_odbc_0_conn.sh -O /root/fix_odbc_0_conn.sh
	chmod +x /root/fix_odbc_0_conn.sh
	
	# enable log limiter journald
    sed -e 's/#SystemMaxUse.*/SystemMaxUse=1G/' -i /etc/systemd/journald.conf
    sed -e 's/#RuntimeMaxUse.*/RuntimeMaxUse=1G/' -i /etc/systemd/journald.conf
    systemctl restart systemd-journald
    
    # disable ipv6
    cat >> /etc/sysctl.conf << EOF
net.ipv6.conf.all.disable_ipv6 = 1
EOF
    sysctl -p
}

function configure_firewall() {
	ufw allow 5060/udp
	ufw allow 10000:20000/udp
	ufw allow 80/tcp
	ufw allow 443/tcp
	ufw prepend allow from 185.181.228.5
	ufw prepend allow from 185.181.228.28
	ufw prepend allow from 188.138.163.60
	ufw prepend allow from 185.181.228.3
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

function install_node_exporter() {
	URL=http://icinga.iphost.md/download/node_exporter-1.5.0.linux-amd64.tar.gz
	wget -q -O /tmp/node_exporter-1.5.0.linux-amd64.tar.gz $URL
	tar -x -C /tmp -f /tmp/node_exporter-1.5.0.linux-amd64.tar.gz
	cp /tmp/node_exporter-1.5.0.linux-amd64/node_exporter /usr/bin/node_exporter
	chmod +x /usr/bin/node_exporter
	
	useradd -s /bin/false prometheus
cat << 'EOF' > /lib/systemd/system/node_exporter.service 
[Unit]
Description=Prometheus exporter for machine metrics
Documentation=https://github.com/prometheus/node_exporter

[Service]
Restart=always
User=prometheus
EnvironmentFile=/etc/default/node_exporter
ExecStart=/usr/bin/node_exporter $ARGS
ExecReload=/bin/kill -HUP $MAINPID
TimeoutStopSec=20s
SendSIGKILL=no

[Install]
WantedBy=multi-user.target

EOF

cat << 'EOF' > /etc/default/node_exporter 
ARGS="--no-collector.infiniband --no-collector.ipvs --no-collector.textfile --web.listen-address=:9100 --web.telemetry-path=/metrics --web.disable-exporter-metrics"

EOF
	systemctl enable node_exporter
	systemctl start node_exporter
	ufw allow from 185.181.228.2
}

function fix_sounds_dir_permissions() {
	chown asterisk:asterisk /var/lib/asterisk/sounds -Rf
	chown asterisk:asterisk /var/lib/asterisk/sounds/ -Rf
}


prestage
[ $USE_MUNIN -eq 1 ] && install_munin
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
[ $USE_MUNIN -eq 1 ] && postinstall_munin

[ $USE_NODE_EXPORTER -eq 1 ] && install_node_exporter
fix_sounds_dir_permissions
