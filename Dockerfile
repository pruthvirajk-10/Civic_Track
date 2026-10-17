# ========================================================
# CivicTrack — Production Dockerfile for Render / Cloud
# PHP 8.2 with Apache and MySQL PDO Driver
# ========================================================

FROM php:8.2-apache

# Install PDO MySQL driver
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Set working directory and copy application source
WORKDIR /var/www/html
COPY . /var/www/html/

# Ensure Apache has ownership of uploads folder for grievance photos
RUN chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

# Support Render's dynamic PORT environment variable (defaults to 80)
ENV PORT=80
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/' /etc/apache2/sites-available/000-default.conf

EXPOSE 80
