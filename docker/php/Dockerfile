FROM php:fpm-alpine3.18

RUN docker-php-ext-configure opcache --enable-opcache \
    && docker-php-ext-install opcache

RUN docker-php-ext-install mysqli pdo pdo_mysql
