FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    curl \
    git \
    zip \
    unzip \
    npm \
    mysql-client \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /app

COPY composer.json composer.lock ./
RUN curl -s https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-interaction --optimize-autoloader

COPY package*.json ./
RUN npm install

COPY . .

RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000 3000

CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=8000 & node server.js"]
