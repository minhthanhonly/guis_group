# Use the official PHP 7.4 Apache image as the base image
# Use a more secure and updated PHP image
FROM php:7.4-apache


# Update the package manager and install security updates
RUN apt-get update && apt-get upgrade -y && apt-get clean

# Enable the rewrite module for Apache
RUN a2enmod rewrite

# Update the Apache configuration to allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Ensure mysqli is installed with mysqlnd
RUN docker-php-ext-install mysqli

# Install additional PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring json curl

# Copy the current directory contents into the container at /var/www/html
COPY . /var/www/html

# Set the working directory
WORKDIR /var/www/html