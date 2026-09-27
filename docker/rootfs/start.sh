#!/bin/bash

echo 'Starting Open Trashmail'

cd /var/www/opentrashmail

# Run as a custom user/group id, eg. to match the owner of mounted folders or SMB shares
if [[ -n "$PUID" || -n "$PGID" ]]; then
  if [[ ! "${PUID:-0}" =~ ^[0-9]+$ || ! "${PGID:-0}" =~ ^[0-9]+$ ]]; then
    echo ' [ERR] PUID and PGID must be numeric'
    exit 1
  fi
  if [[ -n "$PGID" ]]; then
    sed -i -E "s/^nginx:x:[0-9]+:/nginx:x:${PGID}:/" /etc/group
    sed -i -E "s/^(nginx:x:[0-9]+:)[0-9]+:/\1${PGID}:/" /etc/passwd
  fi
  if [[ -n "$PUID" ]]; then
    sed -i -E "s/^nginx:x:[0-9]+:/nginx:x:${PUID}:/" /etc/passwd
  fi
  mkdir -p /run/nginx
  chown -R nginx:nginx /var/lib/nginx /run/nginx /var/log/nginx
fi
echo " [+] Running as uid $(id -u nginx) gid $(id -g nginx)"

echo ' [+] Starting php'
php-fpm83

if [[ ${SKIP_FILEPERMISSIONS:=false} != true ]]; then
  chown -R nginx:nginx /var/www/
  chown -R nginx:nginx /var/www/opentrashmail/data
fi

for dir in data logs; do
  if ! su nginx -s /bin/sh -c "test -w /var/www/opentrashmail/$dir"; then
    echo " [ERR] /var/www/opentrashmail/$dir is not writable by uid $(id -u nginx) gid $(id -g nginx)"
    echo "       Change the owner of the mounted folder (eg. chown -R $(id -u nginx):$(id -g nginx) ./$dir) or set PUID and PGID to its owner"
    exit 1
  fi
done


echo ' [+] Starting nginx'

mkdir -p /var/log/nginx/opentrashmail
touch /var/log/nginx/opentrashmail/web.access.log
touch /var/log/nginx/opentrashmail/web.error.log

mkdir -p /run/nginx
nginx


echo ' [+] Setting up config.ini'



_buildConfig() {
    echo "[GENERAL]"
    echo "DOMAINS=${DOMAINS:-localhost}"
    echo "URL=${URL:-http://localhost:8080}"
    echo "PASSWORD=${PASSWORD:-}"
    echo "ALLOWED_IPS=${ALLOWED_IPS:-}"
    echo "NOTICE=\"${NOTICE//\"/\'}\""
    echo "TRUSTED_PROXIES=${TRUSTED_PROXIES:-}"
    echo ""
    echo "[MAILSERVER]"
    echo "MAILPORT=${MAILPORT:-25}"
    echo "DISCARD_UNKNOWN=${DISCARD_UNKNOWN:-true}"
    echo "ATTACHMENTS_MAX_SIZE=${ATTACHMENTS_MAX_SIZE:-0}"
    echo "MAILPORT_TLS=${MAILPORT_TLS:-0}"
    echo "TLS_CERTIFICATE=${TLS_CERTIFICATE:-}"
    echo "TLS_PRIVATE_KEY=${TLS_PRIVATE_KEY:-}"
    echo "SMTP_HOSTNAME=${SMTP_HOSTNAME:-}"
    echo ""
    echo "[DATETIME]"
    echo "DATEFORMAT=${DATEFORMAT:-D.M.YYYY HH:mm}"
    echo ""
    echo "[CLEANUP]"
    echo "DELETE_OLDER_THAN_DAYS=${DELETE_OLDER_THAN_DAYS:-false}"
    echo ""
    echo "[WEBHOOK]"
    echo "WEBHOOK_URL=${WEBHOOK_URL:-}"
    echo ""
    echo "[ADMIN]"
    echo "ADMIN_ENABLED=${ADMIN_ENABLED:-}"
    echo "ADMIN_PASSWORD=${ADMIN_PASSWORD:-}"
    echo "SHOW_ACCOUNT_LIST=${SHOW_ACCOUNT_LIST:-false}"
    echo "ADMIN=${ADMIN:-}"
    echo "SHOW_LOGS=${SHOW_LOGS:-false}"
}

_buildConfig > /var/www/opentrashmail/config.ini

echo ' [+] Starting Mailserver'
# Started as root so it can bind to port 25 (or any other port below 1024), it then continues as the nginx user.
# The log is written as nginx user too, root might not be allowed to write to network shares
cd /var/www/opentrashmail/python
MAILSERVER_USER=nginx python3 -u mailserver3.py 2>&1 | su nginx -s /bin/sh -c 'cat >> /var/www/opentrashmail/logs/mailserver.log'
