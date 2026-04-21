FROM php:8.3.29-cli-alpine3.23

RUN apk update && apk add --no-cache \
    $PHPIZE_DEPS zip unzip

RUN pecl install pcov && docker-php-ext-enable pcov

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_HOME=/tmp/composer

WORKDIR /var/www/app
