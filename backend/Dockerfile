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

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Copy custom PHP configuration
COPY .docker/php.ini $PHP_INI_DIR/conf.d/custom-php.ini

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions for Laravel and SQLite database directory
RUN mkdir -p /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache \
    && touch /var/www/html/database/database.sqlite \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database

# Copy Apache configuration
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Configure Apache ports to listen on the dynamic $PORT environment variable
RUN echo "Listen \${PORT}" > /etc/apache2/ports.conf

# Default PORT environment variable (Render/Koyeb will override this)
ENV PORT=80

# Configure entrypoint script
RUN chmod +x /var/www/html/.docker/entrypoint.sh

# Expose port
EXPOSE 80

# Start container via resilient entrypoint script
CMD ["/bin/sh", "/var/www/html/.docker/entrypoint.sh"]
