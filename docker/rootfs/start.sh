#!/bin/ash

echo 'Starting Open Trashmail'

cd /var/www/opentrashmail

git pull 



echo ' [+] Starting php'
php-fpm7

chown -R nginx:nginx /var/www/

echo ' [+] Starting nginx'

mkdir -p /var/log/nginx/opentrashmail
touch /var/log/nginx/opentrashmail/web.access.log
touch /var/log/nginx/opentrashmail/web.error.log

nginx


cd /var/www/opentrashmail/python


echo ' [+] Starting Mailserver'
python mailserver.py
#nohup python /var/www/opentrashmail/python/mailserver.py &

#tail -n 1 -f /var/log/nginx/*.log