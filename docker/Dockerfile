FROM alpine:3.18

LABEL org.opencontainers.image.source = "https://github.com/HaschekSolutions/opentrashmail"

RUN apk add --no-cache bash python3 py3-pip socat wget php-fileinfo php-session curl git php php-curl nginx php-openssl php-mbstring php-json php-gd php-dom php-fpm
RUN pip3 install aiosmtpd
RUN pip3 install aiohttp
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
RUN sed -i 's/nobody/nginx/g' /etc/php81/php-fpm.d/www.conf
RUN sed -i 's/E_ALL \& ~E_DEPRECATED \& ~E_STRICT/E_ALL \& ~E_DEPRECATED \& ~E_STRICT \& ~E_NOTICE \& ~E_WARNING/g' /etc/php81/php.ini

WORKDIR /var/www/opentrashmail

# Volumes to mount
VOLUME /var/www/opentrashmail/data
VOLUME /var/www/opentrashmail/logs

EXPOSE 80 25 465

#CMD ["/bin/ash"]
ENTRYPOINT ["/etc/start.sh"]
