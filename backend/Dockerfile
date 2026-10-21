# syntax=docker/dockerfile:1
# Production image: php-fpm 8.3 (alpine). The same image runs the app (php-fpm), queue worker and scheduler (see docker-compose.yml).

# ---- front-end assets ----
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---- PHP dependencies ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# ---- runtime ----
FROM php:8.3-fpm-alpine AS app
RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo libwebp freetype mysql-client supervisor tini \
    && apk add --no-cache --virtual .build $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath gd zip intl pcntl opcache exif \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del .build
COPY deploy/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/private storage/app/media bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 9000
ENTRYPOINT ["/sbin/tini", "--"]
CMD ["php-fpm"]
