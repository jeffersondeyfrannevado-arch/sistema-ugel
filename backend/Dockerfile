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
    pgsql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions for Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy Apache configuration
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# Configure Apache ports to listen on the dynamic $PORT environment variable
RUN echo "Listen \${PORT}" > /etc/apache2/ports.conf

# Default PORT environment variable (Render/Koyeb will override this)
ENV PORT=80

# Expose port
EXPOSE 80

# Start Apache with auto-migration
CMD ["sh", "-c", "php artisan migrate --force && apache2-foreground"]
