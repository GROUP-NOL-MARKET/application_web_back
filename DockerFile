FROM php:8.2-fpm

# Étape 2 : installer les dépendances système
RUN apt-get update && apt-get install -y \
    git curl zip unzip \
    nginx supervisor \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libonig-dev libxml2-dev libzip-dev

# PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create working directory
WORKDIR /var/www

# Copy composer files
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --no-scripts --no-progress --no-interaction || true

# Copy application
COPY . .

# Optimize Laravel
RUN composer install --optimize-autoloader --no-interaction

# Permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage

# Étape 8 : lancer PHP-FPM
CMD ["php-fpm"]
