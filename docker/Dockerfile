FROM alpine:3.24

LABEL org.opencontainers.image.source = "https://github.com/HaschekSolutions/opentrashmail"

RUN apk add --no-cache bash python3 py3-aiosmtpd py3-aiohttp socat wget curl git nginx php83 php83-fpm php83-fileinfo php83-session php83-curl php83-openssl php83-mbstring php83-gd php83-dom
RUN [ -e /usr/bin/php ] || ln -s /usr/bin/php83 /usr/bin/php
#RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer
RUN mkdir -p /var/www/opentrashmail
WORKDIR /var/www/opentrashmail

ADD . /var/www/opentrashmail/.

ADD docker/rootfs/start.sh /etc/start.sh
RUN chmod +x /etc/start.sh

# nginx stuff
ADD docker/rootfs/nginx.conf /etc/nginx/http.d/default.conf
RUN mkdir -p /run/nginx
RUN mkdir -p /var/log/nginx
RUN sed -i 's/nobody/nginx/g' /etc/php83/php-fpm.d/www.conf
RUN sed -i 's/^error_reporting = .*/error_reporting = E_ALL \& ~E_DEPRECATED \& ~E_STRICT \& ~E_NOTICE \& ~E_WARNING/' /etc/php83/php.ini
# don't advertise the PHP version in the X-Powered-By header
RUN sed -i 's/^expose_php = .*/expose_php = Off/' /etc/php83/php.ini

WORKDIR /var/www/opentrashmail

# Volumes to mount
VOLUME /var/www/opentrashmail/data
VOLUME /var/www/opentrashmail/logs

EXPOSE 80 25 465

# Security stopgap for GHSA-6wmf-4rv5-p3rp / GHSA-w8hw-w9fx-j79w (2026-10-21).
# Keep upload progress off even after patching; fatal_error_backtraces only exists in PHP 8.5+.
RUN for d in /usr/local/etc/php/conf.d /etc/php*/conf.d; do \
      [ -d "$d" ] && printf 'session.upload_progress.enabled = 0\nfatal_error_backtraces = 0\n' > "$d/zz-security.ini"; \
    done; true

#CMD ["/bin/ash"]
ENTRYPOINT ["/etc/start.sh"]
