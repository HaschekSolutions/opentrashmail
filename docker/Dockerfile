FROM alpine:3.14.2

RUN apk add --no-cache python2 socat wget php7-fileinfo php7-session curl git php php-curl nginx php-openssl php-mbstring php-json php-gd php-dom php-fpm
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
RUN sed -i 's/nobody/nginx/g' /etc/php7/php-fpm.d/www.conf

WORKDIR /var/www/opentrashmail

# Volumes to mount
#VOLUME /var/lib/influxdb
VOLUME /var/www/opentrashmail/data

EXPOSE 80 25

#CMD ["/bin/ash"]
ENTRYPOINT ["/etc/start.sh"]