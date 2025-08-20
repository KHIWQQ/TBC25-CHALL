FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    vim \
    nano \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache \
    && docker-php-ext-enable opcache \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite
RUN a2enmod headers
RUN service apache2 restart

# Create log directory
RUN mkdir -p /var/log/apache2 && \
    chown -R www-data:www-data /var/log/apache2

# Copy custom PHP configuration
COPY php.ini /usr/local/etc/php/php.ini

# Set working directory
WORKDIR /var/www/html

# Copy custom Apache configuration
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Create uploads directory with proper permissions
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Copy application files
COPY src/ /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 777 /var/www/html/uploads

EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1