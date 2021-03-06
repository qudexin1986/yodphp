#!/bin/bash

YOD_CODE=/mnt/hgfs/E/develop/github/yodphp/master

cp -rfv ${YOD_CODE}/yod/* .

cp -rfv ${YOD_CODE}/demo/* /var/www/html
cp -rfv ${YOD_CODE}/yodphp/* /var/www/html

rm -rfv core.*
rm -rfv tests/core.*

if [ x$1 != x ]
then

./configure ${2} --with-php-config=/usr/local/php/${1}/bin/php-config

else

./configure --enable-yod-debug

fi

make clean && make && make install

if [ x$1 != x ]
then

/usr/local/php/${1}/bin/php -f /var/www/html/index.php

/usr/local/php/${1}/bin/php -m

make test

else

service httpd restart

php -f /var/www/html/index.php

fi

