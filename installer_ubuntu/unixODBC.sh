#!/bin/bash

emerge -avu unixODBC
wget https://dev.mysql.com/get/Downloads/Connector-ODBC/5.3/mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit.tar.gz
tar xvf mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit.tar.gz
cp mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit/lib/*.so /usr/lib64 -v

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