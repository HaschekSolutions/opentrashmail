#!/bin/bash

echo 'Starting Open Trashmail'

cd /var/www/opentrashmail

echo ' [+] Starting php'
php-fpm81

if [[ ${SKIP_FILEPERMISSIONS:=false} != true ]]; then
  chown -R nginx:nginx /var/www/
  chown -R nginx:nginx /var/www/opentrashmail/data
fi


echo ' [+] Starting nginx'

mkdir -p /var/log/nginx/opentrashmail
touch /var/log/nginx/opentrashmail/web.access.log
touch /var/log/nginx/opentrashmail/web.error.log

nginx


echo ' [+] Setting up config.ini'



_buildConfig() {
    echo "[GENERAL]"
    echo "DOMAINS=${DOMAINS:-localhost}"
    echo "URL=${URL:-http://localhost:8080}"
    echo "SHOW_ACCOUNT_LIST=${SHOW_ACCOUNT_LIST:-false}"
    echo "ADMIN=${ADMIN:-}"
    echo "SHOW_LOGS=${SHOW_LOGS:-false}"
    echo "PASSWORD=${PASSWORD:-}"
    echo "ALLOWED_IPS=${ALLOWED_IPS:-}"

    echo "[MAILSERVER]"
    echo "MAILPORT=${MAILPORT:-25}"
    echo "DISCARD_UNKNOWN=${DISCARD_UNKNOWN:-true}"
    echo "ATTACHMENTS_MAX_SIZE=${ATTACHMENTS_MAX_SIZE:-0}"

    echo "[DATETIME]"
    echo "DATEFORMAT=${DATEFORMAT:-D.M.YYYY HH:mm}"

    echo "[CLEANUP]"
    echo "DELETE_OLDER_THAN_DAYS=${DELETE_OLDER_THAN_DAYS:-false}"
}

_buildConfig > /var/www/opentrashmail/config.ini

echo ' [+] Starting Mailserver'
su - nginx -s /bin/ash -c 'cd /var/www/opentrashmail/python;python3 -u mailserver3.py >> /var/www/opentrashmail/logs/mailserver.log 2>&1 '
