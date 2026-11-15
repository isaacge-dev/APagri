FROM php:8.2-apache

# Install PostgreSQL client development libraries and PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Use production PHP configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Set working directory
WORKDIR /var/www/html

# Copy application files to web root
COPY . /var/www/html/

# Set file permissions for Apache web server user
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose Render default port
EXPOSE 10000

# Dynamically bind Apache to Render's $PORT (fallback to 10000) and start Apache
CMD ["sh", "-c", "sed -i \"s/80/${PORT:-10000}/g\" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
