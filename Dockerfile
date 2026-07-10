FROM php:8.2-apache

# Enable required PHP extensions
RUN docker-php-ext-install mysqli

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set the working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Ensure uploads directory is writable
RUN mkdir -p uploads && chown -R www-data:www-data uploads

# Use the default Apache document root (/var/www/html)
EXPOSE 80
