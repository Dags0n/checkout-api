FROM php:8.4-cli-alpine

RUN apk add --no-cache \
    git curl libpq-dev oniguruma-dev libzip-dev icu-dev \
    autoconf gcc g++ make linux-headers \
    && docker-php-ext-install pdo pdo_pgsql bcmath intl zip \
    && apk del autoconf gcc g++ make linux-headers

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-dev

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
