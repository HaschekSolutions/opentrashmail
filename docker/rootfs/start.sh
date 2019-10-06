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


echo ' [+] Setting up config.ini'

echo "[GENERAL]" > /var/www/opentrashmail/config.ini
if [ "$DOMAINS" != "" ]; then
	echo "DOMAINS=$DOMAINS" >> /var/www/opentrashmail/config.ini
    echo "   [i] Active Domain(s): $DOMAINS"
fi

if [ "$ADMIN" != "" ]; then
	echo "ADMIN=$ADMIN" >> /var/www/opentrashmail/config.ini
    echo "   [i] Set admin to: $ADMIN"
fi

echo "[MAILSERVER]" >> /var/www/opentrashmail/config.ini
echo "MAILPORT=25" >> /var/www/opentrashmail/config.ini

echo "[DATETIME]" >> /var/www/opentrashmail/config.ini
echo "DATEFORMAT='D.M.YYYY HH:mm'" >> /var/www/opentrashmail/config.ini

cd /var/www/opentrashmail/python

echo ' [+] Starting Mailserver'
python mailserver.py
#nohup python /var/www/opentrashmail/python/mailserver.py &

#tail -n 1 -f /var/log/nginx/*.log