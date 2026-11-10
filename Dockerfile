FROM php:8.3-apache-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates git unzip libpng-dev libonig-dev libpq-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" gd mbstring pdo_pgsql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/geosur/web
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr DB_CONNECTION=pgsql SESSION_DRIVER=file CACHE_STORE=file
ENV APACHE_DOCUMENT_ROOT=/var/www/geosur/web/public
RUN sed -ri 's!/var/www/html!/var/www/geosur/web/public!g' /etc/apache2/sites-available/*.conf \
    && printf '<Directory /var/www/geosur/web/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/geosur.conf \
    && a2enconf geosur
COPY web/ ./
COPY compartido/ ../compartido/
RUN mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts \
    && php artisan package:discover --ansi
COPY herramientas/despliegue/iniciar.sh /usr/local/bin/geosur-iniciar
RUN chmod +x /usr/local/bin/geosur-iniciar \
    && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8080
CMD ["geosur-iniciar"]
