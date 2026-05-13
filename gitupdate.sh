#!/bin/sh

chown -R www-root:www-root /var/www/www-root/data/www/progs.com/storage/
chmod -R 777 /var/www/www-root/data/www/progs.com/storage/
for d in /var/www/www-root/data/www/progs.com/session.madeline /var/www/www-root/data/www/progs.com/session.madeline.*; do
    [ -e "$d" ] || continue
    chown -R www-root:www-root "$d"
    chmod -R 777 "$d"
done
chown www-root:www-root /var/www/www-root/data/www/progs.com/MadelineProto.log
chmod 777 /var/www/www-root/data/www/progs.com/MadelineProto.log
php /var/www/www-root/data/www/progs.com/artisan optimize 
php /var/www/www-root/data/www/progs.com/artisan schedule:interrupt
