# Multi-stage Dockerfile for PPV Streaming Platform

# Build stage
FROM composer:2.6 AS composer
WORKDIR /app
COPY composer.json ./
RUN composer update --no-dev --optimize-autoloader --no-scripts

# Production stage
FROM php:8.1-fpm-alpine AS production

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    mysql-client \
    curl \
    zip \
    unzip \
    git \
    && docker-php-ext-install \
    pdo_mysql \
    && rm -rf /var/cache/apk/*

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Create application user
RUN addgroup -g 1000 app && \
    adduser -D -u 1000 -G app app

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY --from=composer /app/vendor vendor/
COPY . .
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh

# Create necessary directories and set permissions
RUN mkdir -p /var/log/supervisor /var/log/nginx /var/log/php \
    && chown -R app:app /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod +x /entrypoint.sh

# PHP configuration
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/memory-limit.ini \
    && echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/upload.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/upload.ini \
    && echo "max_execution_time = 300" > /usr/local/etc/php/conf.d/execution-time.ini

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
  CMD curl -f http://localhost/health || exit 1

# Expose port
EXPOSE 80

# Set user
USER app

# Start services
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Development stage
FROM production AS development

USER root

# Install development dependencies
RUN apk add --no-cache \
    nodejs \
    npm

# Development dependencies are already included in vendor/

USER app