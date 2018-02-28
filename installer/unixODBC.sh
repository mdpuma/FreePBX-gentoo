#!/bin/bash

emerge -avu unixODBC
wget https://dev.mysql.com/get/Downloads/Connector-ODBC/5.3/mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit.tar.gz
tar xvf mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit.tar.gz
cp mysql-connector-odbc-5.3.10-linux-glibc2.12-x86-64bit/lib/*.so /usr/lib64 -v