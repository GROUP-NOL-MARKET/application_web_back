FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip \
    nginx supervisor \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libzip-dev

RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-progress --no-interaction || true

COPY . .

RUN composer install --optimize-autoloader --no-interaction

# Permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage

# Copy Nginx config
COPY ./nginx.conf /etc/nginx/sites-available/default

# Copy Supervisord config
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose HTTP port
EXPOSE 80

# Start Supervisor (which runs nginx + php-fpm)
CMD ["/usr/bin/supervisord"]
