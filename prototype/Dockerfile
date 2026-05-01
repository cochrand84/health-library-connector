FROM php:8.3-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Copy the PHP prototype into Apache's web root as index.php
COPY mayo-library.php /var/www/html/index.php

EXPOSE 80
