FROM php:8.3-apache

RUN a2enmod rewrite \
    && printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

COPY . /var/www/html/
COPY docker-start.sh /usr/local/bin/docker-start.sh

RUN chmod +x /usr/local/bin/docker-start.sh \
    && mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/data

EXPOSE 10000
CMD ["docker-start.sh"]
