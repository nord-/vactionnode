FROM php:8.2-apache

# Composer finns inte på värden, så den bor i imagen. vendor/ hamnar i
# den monterade repo-roten och deployas aldrig till NAS:en.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
 && apt-get install -y --no-install-recommends unzip \
 && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo_mysql \
 && docker-php-ext-enable opcache \
 && a2enmod rewrite
RUN printf 'opcache.enable=1\nopcache.validate_timestamps=1\n' \
      > /usr/local/etc/php/conf.d/opcache.ini
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
WORKDIR /var/www/html
