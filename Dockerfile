FROM php:8.3-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libpq-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    gd \
    zip \
    xml \
    bcmath \
    pdo_pgsql \
    pgsql \
    pdo_mysql

# Enable Apache rewrite module and keep prefork MPM
RUN a2dismod -f mpm_event mpm_worker || true \
    && a2enmod mpm_prefork rewrite headers

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy backend files into container
COPY backend/ .

# Copy custom PHP configuration
COPY backend/.docker/php.ini $PHP_INI_DIR/conf.d/custom-php.ini

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

RUN mkdir -p /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache \
    && touch /var/www/html/database/database.sqlite \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

COPY backend/.docker/vhost.conf /etc/apache2/sites-available/000-default.conf
RUN echo "Listen \${PORT}" > /etc/apache2/ports.conf

ENV PORT=80

RUN sed -i 's/\r$//' /var/www/html/.docker/entrypoint.sh \
    && chmod +x /var/www/html/.docker/entrypoint.sh

EXPOSE 80
CMD ["/bin/sh", "/var/www/html/.docker/entrypoint.sh"]
