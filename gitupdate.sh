#!/bin/sh

chown -R www-root:www-root /var/www/www-root/data/www/progs.com/storage/
chmod -R 777 /var/www/www-root/data/www/progs.com/storage/
chown -R www-root:www-root /var/www/www-root/data/www/progs.com/session.madeline
chmod -R 777 /var/www/www-root/data/www/progs.com/session.madeline
chown www-root:www-root /var/www/www-root/data/www/progs.com/MadelineProto.log
chmod 777 /var/www/www-root/data/www/progs.com/MadelineProto.log
php /var/www/www-root/data/www/progs.com/artisan optimize 
php /var/www/www-root/data/www/progs.com/artisan schedule:interrupt
