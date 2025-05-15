# Use the official PHP image with Apache
FROM php:8.2-apache

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy all files to the container
COPY . /var/www/html/

# Set proper permissions for the web server
RUN chown -R www-data:www-data /var/www/html \
    && chmod 755 /var/www/html \
    && chmod 644 /var/www/html/.htaccess \
    && chmod 666 /var/www/html/users.json \
    && chmod 666 /var/www/html/error.log

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]