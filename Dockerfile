FROM php:8.2-apache

# Install PostgreSQL client development library
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Install the pdo_pgsql extension
RUN docker-php-ext-install pdo pdo_pgsql

# Copy the application source code
COPY . /var/www/html/

# Set ownership to Apache user (www-data) so uploads are permitted
RUN chown -R www-data:www-data /var/www/html/

# Expose port 80 (default Apache port)
EXPOSE 80
