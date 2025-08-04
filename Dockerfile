FROM php:8.2-apache

# Installa estensione MySQL o PostgreSQL
RUN docker-php-ext-install mysqli

# Copia i file PHP nel container
COPY ./backend/ /var/www/html/

# Abilita mod_rewrite se serve
RUN a2enmod rewrite
