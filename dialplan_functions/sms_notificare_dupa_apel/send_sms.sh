#!/bin/bash

PASSWORD=3agd1DQXC6Yexx
NUMBER=078074455
#MSG="Ati discutat cu operator Andrei 078074455, Compania ANDIVA"
MSG="Ați+discutat+cu+operator+Andrei+078074455%2C+Compania+ANDIVA"
GSMLINE=1

curl "http://192.168.10.198/default/en_US/send.html?u=admin&p=$PASSWORD&l=$GSMLINE&n=$NUMBER&m=$MSG" -v