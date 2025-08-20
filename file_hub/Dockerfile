FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Configure nginx
COPY nginx.conf /etc/nginx/sites-available/default

# Set working directory
WORKDIR /var/www/html

# Create uploads directory with proper permissions
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Start script
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]