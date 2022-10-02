#!/bin/ash

echo 'Starting Open Trashmail'

cd /var/www/opentrashmail

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
else
  echo "DOMAINS=localhost" >> /var/www/opentrashmail/config.ini
fi

if [ "$FAKENAME_GENERATOR" != "" ]; then
  echo "   [i] Enabled fake name generator"
	echo "; Generate fake usernames from https://randomuser.me/" >> /var/www/opentrashmail/config.ini
  echo "FAKENAME_GENERATOR=$FAKENAME_GENERATOR" >> /var/www/opentrashmail/config.ini
  echo "; Customise the username using the options available at https://randomuser.me/documentation" >> /var/www/opentrashmail/config.ini
  echo ";   - These options will be added after the '?' eg: https://randomuser.me/api/?{FAKENAME_SUFFIX}" >> /var/www/opentrashmail/config.ini
  if [ "$FAKENAME_SUFFIX" != "" ]; then
    echo "     [i] With suffix: $FAKENAME_SUFFIX "
    echo "FAKENAME_SUFFIX=\"$FAKENAME_SUFFIX\"" >> /var/www/opentrashmail/config.ini
  else
    echo "; FAKENAME_SUFFIX=\"gender=male\"" >> /var/www/opentrashmail/config.ini
  fi
else
  echo "; Generate fake usernames from https://randomuser.me/" >> /var/www/opentrashmail/config.ini
  echo "; FAKENAME_GENERATOR=true" >> /var/www/opentrashmail/config.ini
  echo "; Customise the username using the options available at https://randomuser.me/documentation" >> /var/www/opentrashmail/config.ini
  echo ";   - These options will be added after the '?' eg: https://randomuser.me/api/?{FAKENAME_SUFFIX}" >> /var/www/opentrashmail/config.ini
  echo "; FAKENAME_SUFFIX=\"gender=male\"" >> /var/www/opentrashmail/config.ini
fi

if [ "$ADMIN" != "" ]; then
	echo "ADMIN=$ADMIN" >> /var/www/opentrashmail/config.ini
  echo "   [i] Set admin to: $ADMIN"
fi

echo "[MAILSERVER]" >> /var/www/opentrashmail/config.ini
echo "MAILPORT=25" >> /var/www/opentrashmail/config.ini
if [ "$DISCARD_UNKNOWN" != "" ]; then
	echo "DISCARD_UNKNOWN=$DISCARD_UNKNOWN" >> /var/www/opentrashmail/config.ini
  echo "   [i] Setting up DISCARD_UNKNOWN to: $DISCARD_UNKNOWN"
else
  echo "DISCARD_UNKNOWN=false" >> /var/www/opentrashmail/config.ini
fi

echo "[DATETIME]" >> /var/www/opentrashmail/config.ini
if [ "$DATEFORMAT" != "" ]; then
	echo "DATEFORMAT=$DATEFORMAT" >> /var/www/opentrashmail/config.ini
  echo "   [i] Setting up dateformat to: $DATEFORMAT"
else
  echo "DATEFORMAT='D.M.YYYY HH:mm'" >> /var/www/opentrashmail/config.ini
  echo "   [i] Using default dateformat"
fi

echo "[CLEANUP]" >> /var/www/opentrashmail/config.ini
if [ "$DELETE_OLDER_THAN_DAYS" != "" ]; then
	echo "DELETE_OLDER_THAN_DAYS=$DELETE_OLDER_THAN_DAYS" >> /var/www/opentrashmail/config.ini
  echo "   [i] Setting up cleanup time to $DELETE_OLDER_THAN_DAYS days"
fi

chown -R nginx:nginx /var/www/opentrashmail/data

echo ' [+] Starting Mailserver'
su - nginx -s /bin/ash -c 'cd /var/www/opentrashmail/python;python mailserver.py'
