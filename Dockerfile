FROM php:7.4-cli

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV XDEBUG_MODE=coverage

WORKDIR /code

RUN apt-get update && apt-get install -y \
        git \
        unzip \
   --no-install-recommends && rm -r /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/php.ini

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer

# lock to Xdebug 3.1.6 until we get rid of PHP 7.4
RUN pecl install xdebug-3.1.6 \
    && docker-php-ext-enable xdebug

COPY composer.* ./
RUN composer install $COMPOSER_FLAGS --no-scripts --no-autoloader
COPY . .
RUN composer install $COMPOSER_FLAGS
