# Base Image
FROM php:8.3-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
  nginx \
  libpng-dev \
  libjpeg-dev \
  libfreetype6-dev \
  zip \
  unzip \
  curl \
  git \
  supervisor \
  sqlite3 \
  libzip-dev \
  libxml2-dev \
  libonig-dev \
  libprotobuf-dev \
  protobuf-compiler \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install gd pdo_mysql zip xml mbstring bcmath

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install gRPC silently
RUN pecl install grpc > /dev/null 2>&1 && echo "extension=grpc.so" > /usr/local/etc/php/conf.d/grpc.ini

# Copy Nginx configuration
COPY .docker/nginx.conf /etc/nginx/nginx.conf
COPY .docker/default.conf /etc/nginx/sites-available/default

# Set working directory (but don't copy Laravel files)
WORKDIR /var/www

# Expose ports for Nginx
EXPOSE 80

# Start services
CMD service nginx start && php-fpm
