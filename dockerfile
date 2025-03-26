# Используем официальный образ PHP с Apache
FROM php:8.2-apache

# Устанавливаем зависимости
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev pkg-config libssl-dev unzip

# Устанавливаем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Устанавливаем MongoDB расширение для PHP
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Устанавливаем модули, необходимые для работы PHP
RUN docker-php-ext-install pdo pdo_mysql

# Копируем файлы проекта
WORKDIR /var/www/html
COPY . .

# Открываем порт для Apache
EXPOSE 80
