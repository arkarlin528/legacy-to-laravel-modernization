# PHP-FPM + nginx in one image (serversideup/php), with OPcache on. Listens on 8080.
FROM serversideup/php:8.3-fpm-nginx

USER root
RUN install-php-extensions intl bcmath
USER www-data

WORKDIR /var/www/html
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan filament:assets

# serversideup runs these on start: migrations (and caches config/routes/views for speed).
ENV AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    PHP_OPCACHE_ENABLE=1
