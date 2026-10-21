FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
COPY Modules/Core/composer.json Modules/Core/composer.json
COPY Modules/Auth/composer.json Modules/Auth/composer.json
COPY Modules/Order/composer.json Modules/Order/composer.json
COPY Modules/Payment/composer.json Modules/Payment/composer.json
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && composer dump-autoload --optimize \
    && php artisan package:discover --ansi

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
