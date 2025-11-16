# Legal Document & Contract Analyzer
# Production Docker Image

FROM php:7.4-apache

# Metadata
LABEL maintainer="Legal Analysis Team"
LABEL version="4.1"
LABEL description="Legal Document & Contract Analyzer - Knuth · Wolfram · Torvalds"

# Install required PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install \
    opcache \
    && rm -rf /var/lib/apt/lists/*

# Install APCu for caching
RUN pecl install apcu \
    && docker-php-ext-enable apcu

# Configure PHP for production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo "opcache.enable=1" >> "$PHP_INI_DIR/conf.d/opcache.ini" && \
    echo "opcache.memory_consumption=128" >> "$PHP_INI_DIR/conf.d/opcache.ini" && \
    echo "opcache.interned_strings_buffer=8" >> "$PHP_INI_DIR/conf.d/opcache.ini" && \
    echo "opcache.max_accelerated_files=10000" >> "$PHP_INI_DIR/conf.d/opcache.ini" && \
    echo "apc.enabled=1" >> "$PHP_INI_DIR/conf.d/apcu.ini" && \
    echo "apc.shm_size=32M" >> "$PHP_INI_DIR/conf.d/apcu.ini"

# Enable Apache modules
RUN a2enmod rewrite headers deflate

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY --chown=www-data:www-data . /var/www/html/

# Create necessary directories
RUN mkdir -p logs cache tmp backups && \
    chown -R www-data:www-data logs cache tmp backups && \
    chmod -R 777 logs cache tmp

# Set file permissions
RUN find /var/www/html -type f -name "*.php" -exec chmod 644 {} \; && \
    find /var/www/html -type d -exec chmod 755 {} \;

# Apache configuration
COPY <<EOF /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80>
    ServerAdmin admin@localhost
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Security headers
        Header set X-Content-Type-Options "nosniff"
        Header set X-Frame-Options "SAMEORIGIN"
        Header set X-XSS-Protection "1; mode=block"
        Header set Referrer-Policy "strict-origin-when-cross-origin"
    </Directory>

    # Block sensitive directories
    <DirectoryMatch "/(logs|cache|backups|tmp|\.git)">
        Require all denied
    </DirectoryMatch>

    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined

    # Enable compression
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
    </IfModule>
</VirtualHost>
EOF

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/qsgx_v2.php || exit 1

# Expose port 80
EXPOSE 80

# Run Apache in foreground
CMD ["apache2-foreground"]
