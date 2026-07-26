# Usar la imagen oficial de PHP con Apache
FROM php:8.2-apache

# Instalar las librerías necesarias para compilar/usar el driver de PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql

# Copiar todos los archivos del proyecto al directorio web de Apache
COPY . /var/www/html/

# Habilitar el puerto 80 de Apache
EXPOSE 80
