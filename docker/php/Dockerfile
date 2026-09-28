# Laravel API image (PHP-FPM). Used by the "app" and "queue" services.
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        bash \
        freetype \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libwebp \
        libzip \
        mysql-client \
        ttf-dejavu \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl opcache pcntl pdo_mysql zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint

WORKDIR /var/www/html

# Dependencies first for better layer caching.
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY backend/ ./
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
