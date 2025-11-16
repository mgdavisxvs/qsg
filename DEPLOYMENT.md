# Deployment Guide
## Legal Document & Contract Analyzer v4.1

**Last Updated**: 2025-11-16
**Version**: 4.1 (Knuth · Wolfram · Torvalds)

---

## Table of Contents

1. [Quick Start](#quick-start)
2. [System Requirements](#system-requirements)
3. [Installation Methods](#installation-methods)
4. [Environment Configuration](#environment-configuration)
5. [Web Server Setup](#web-server-setup)
6. [Security Hardening](#security-hardening)
7. [Monitoring & Maintenance](#monitoring--maintenance)
8. [Troubleshooting](#troubleshooting)
9. [Rollback Procedure](#rollback-procedure)

---

## Quick Start

### For Development

```bash
# Clone repository
git clone https://github.com/your-org/legal-analyzer.git
cd legal-analyzer

# Deploy in development mode
./deploy.sh dev

# Access at: http://localhost/qsgx_v2.php
```

### For Production

```bash
# Clone to production directory
git clone https://github.com/your-org/legal-analyzer.git /var/www/legal-analyzer
cd /var/www/legal-analyzer

# Run deployment script
sudo ./deploy.sh production

# Configure SSL and web server
# Access at: https://your-domain.com
```

---

## System Requirements

### Minimum Requirements

- **OS**: Linux (Ubuntu 20.04+, Debian 10+, CentOS 7+) or macOS
- **PHP**: 7.4 or higher
- **Memory**: 256MB RAM minimum
- **Disk**: 100MB free space
- **Web Server**: Apache 2.4+ or Nginx 1.18+

### Required PHP Extensions

```bash
# Check installed extensions
php -m

# Required:
- mbstring      # Multi-byte string support
- json          # JSON parsing
- session       # Session management
- pcre          # Regular expressions

# Recommended:
- opcache       # Performance (5-10x speedup)
- apcu          # Caching (10-100x speedup)
```

### Installing PHP Extensions (Ubuntu/Debian)

```bash
sudo apt-get update
sudo apt-get install -y \
    php7.4 \
    php7.4-mbstring \
    php7.4-json \
    php7.4-opcache \
    php7.4-apcu
```

### Installing PHP Extensions (CentOS/RHEL)

```bash
sudo yum install -y \
    php74 \
    php74-mbstring \
    php74-json \
    php74-opcache \
    php74-pecl-apcu
```

---

## Installation Methods

### Method 1: Automated Deployment (Recommended)

```bash
# Standard deployment
./deploy.sh production

# Development deployment
./deploy.sh dev

# With options
./deploy.sh production --skip-tests
./deploy.sh staging --dry-run
```

### Method 2: Manual Deployment

```bash
# 1. Create directory structure
mkdir -p logs cache backups tmp
chmod 777 logs cache tmp

# 2. Set file permissions
find . -type f -name "*.php" -exec chmod 644 {} \;
chmod 755 deploy.sh

# 3. Generate configuration
cp .env.example .env
nano .env  # Edit configuration

# 4. Run tests
php tests.php
php test_knuth_fixes.php
php test_wolfram.php

# 5. Configure web server (see below)
```

### Method 3: Docker Deployment

```bash
# Build Docker image
docker build -t legal-analyzer:4.1 .

# Run container
docker run -d \
    -p 80:80 \
    -v $(pwd)/logs:/app/logs \
    -v $(pwd)/cache:/app/cache \
    --name legal-analyzer \
    legal-analyzer:4.1

# Access at: http://localhost
```

---

## Environment Configuration

### Environment Variables (.env file)

```bash
# Application
APP_ENV=production              # dev, staging, production
APP_DEBUG=false                 # true for development
APP_VERSION=4.1

# Caching
CACHE_ENABLED=true              # false for development
CACHE_MAX_SIZE=1000             # Max cache entries
CACHE_TTL=3600                  # Cache lifetime (seconds)

# Logging
LOG_LEVEL=warning               # debug, info, warning, error
LOG_FILE=logs/app.log
LOG_MAX_SIZE=10485760          # 10MB

# Security
CSRF_TOKEN_LENGTH=32
RATE_LIMIT_REQUESTS=10          # Max requests per window
RATE_LIMIT_WINDOW=60            # Window in seconds
SESSION_LIFETIME=3600           # 1 hour

# PHP Settings
PHP_ERROR_REPORTING=E_ALL & ~E_DEPRECATED & ~E_STRICT
PHP_DISPLAY_ERRORS=Off          # On for development
PHP_MAX_EXECUTION_TIME=30
PHP_MEMORY_LIMIT=256M
```

### Environment-Specific Settings

#### Development
```bash
APP_ENV=development
APP_DEBUG=true
CACHE_ENABLED=false
LOG_LEVEL=debug
PHP_DISPLAY_ERRORS=On
```

#### Staging
```bash
APP_ENV=staging
APP_DEBUG=false
CACHE_ENABLED=true
LOG_LEVEL=info
PHP_DISPLAY_ERRORS=Off
```

#### Production
```bash
APP_ENV=production
APP_DEBUG=false
CACHE_ENABLED=true
LOG_LEVEL=warning
PHP_DISPLAY_ERRORS=Off
```

---

## Web Server Setup

### Apache Configuration

**Option 1: Using .htaccess (Easier)**

The deployment script creates `.htaccess` automatically. Ensure `mod_rewrite` is enabled:

```bash
sudo a2enmod rewrite
sudo a2enmod headers
sudo systemctl restart apache2
```

**Option 2: VirtualHost (Better Performance)**

```apache
<VirtualHost *:80>
    ServerName legal-analyzer.example.com
    DocumentRoot /var/www/legal-analyzer

    <Directory /var/www/legal-analyzer>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Security headers
        Header set X-Content-Type-Options "nosniff"
        Header set X-Frame-Options "SAMEORIGIN"
        Header set X-XSS-Protection "1; mode=block"
    </Directory>

    # Block sensitive directories
    <DirectoryMatch "/(logs|cache|backups|tmp|\.git)">
        Require all denied
    </DirectoryMatch>

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/legal-analyzer-error.log
    CustomLog ${APACHE_LOG_DIR}/legal-analyzer-access.log combined

    # Enable compression
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
    </IfModule>
</VirtualHost>
```

Enable site:
```bash
sudo a2ensite legal-analyzer
sudo systemctl reload apache2
```

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name legal-analyzer.example.com;

    root /var/www/legal-analyzer;
    index qsgx_v2.php;

    # Logging
    access_log /var/log/nginx/legal-analyzer-access.log;
    error_log /var/log/nginx/legal-analyzer-error.log;

    # Security headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Block hidden files and sensitive directories
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }

    location ~ ^/(logs|cache|backups|tmp|\.git|\.env) {
        deny all;
        access_log off;
    }

    # PHP handling
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        # PHP settings
        fastcgi_param PHP_VALUE "
            upload_max_filesize=10M
            post_max_size=10M
            max_execution_time=30
            memory_limit=256M
            display_errors=off
            log_errors=on
            error_log=/var/www/legal-analyzer/logs/php_errors.log
        ";
    }

    # Compression
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/json;
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/legal-analyzer /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## Security Hardening

### SSL/TLS Configuration

**Using Let's Encrypt (Free)**:

```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-apache  # Apache
# OR
sudo apt-get install certbot python3-certbot-nginx   # Nginx

# Obtain certificate
sudo certbot --apache -d legal-analyzer.example.com  # Apache
# OR
sudo certbot --nginx -d legal-analyzer.example.com   # Nginx

# Auto-renewal (runs twice daily)
sudo systemctl enable certbot.timer
```

**Apache SSL VirtualHost**:

```apache
<VirtualHost *:443>
    ServerName legal-analyzer.example.com
    DocumentRoot /var/www/legal-analyzer

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/legal-analyzer.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/legal-analyzer.example.com/privkey.pem

    # Modern SSL configuration
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256
    SSLHonorCipherOrder off
    SSLSessionTickets off

    # HSTS (HTTP Strict Transport Security)
    Header always set Strict-Transport-Security "max-age=63072000"

    # ... (rest of configuration)
</VirtualHost>
```

### Firewall Configuration

```bash
# UFW (Ubuntu/Debian)
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable

# firewalld (CentOS/RHEL)
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

### File Permissions Security

```bash
# Application files: read-only for web server
find /var/www/legal-analyzer -type f -name "*.php" -exec chmod 644 {} \;
find /var/www/legal-analyzer -type d -exec chmod 755 {} \;

# Writable directories: only logs, cache, tmp
chmod 777 /var/www/legal-analyzer/{logs,cache,tmp}

# Sensitive files: owner-only
chmod 600 /var/www/legal-analyzer/.env
chmod 600 /var/www/legal-analyzer/backups/*

# Disable execution in writable directories
# (Add to .htaccess or nginx config)
```

### Additional Security Measures

1. **Disable PHP Functions** (php.ini):
```ini
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source
```

2. **Rate Limiting** (Already implemented in code):
   - 10 requests per 60 seconds per IP
   - Configurable in .env

3. **CSRF Protection** (Already implemented):
   - Token-based protection on all forms
   - Automatic token regeneration

4. **Input Validation** (Already implemented):
   - 1-10,000 character limit
   - XSS prevention with htmlspecialchars

---

## Monitoring & Maintenance

### Log Monitoring

```bash
# View application logs
tail -f /var/www/legal-analyzer/logs/app.log

# View PHP errors
tail -f /var/www/legal-analyzer/logs/php_errors.log

# View deployment history
cat /var/www/legal-analyzer/logs/deployment.log

# Search for errors
grep -i "error" /var/www/legal-analyzer/logs/app.log
```

### Performance Monitoring

```bash
# Check PHP-FPM status
sudo systemctl status php7.4-fpm

# Monitor cache hit rate
grep "Cache" /var/www/legal-analyzer/logs/app.log | tail -20

# Check disk usage
du -sh /var/www/legal-analyzer/*
```

### Automated Backups

```bash
# Create backup script
cat > /usr/local/bin/backup-legal-analyzer.sh <<'EOF'
#!/bin/bash
BACKUP_DIR="/var/backups/legal-analyzer"
SOURCE_DIR="/var/www/legal-analyzer"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p "$BACKUP_DIR"
tar -czf "$BACKUP_DIR/backup_$DATE.tar.gz" \
    --exclude='cache/*' \
    --exclude='logs/*' \
    --exclude='tmp/*' \
    -C "$SOURCE_DIR" .

# Keep only last 30 days
find "$BACKUP_DIR" -name "backup_*.tar.gz" -mtime +30 -delete
EOF

chmod +x /usr/local/bin/backup-legal-analyzer.sh

# Add to crontab (daily at 2 AM)
crontab -e
# Add: 0 2 * * * /usr/local/bin/backup-legal-analyzer.sh
```

### Log Rotation

```bash
# Create logrotate configuration
sudo nano /etc/logrotate.d/legal-analyzer

# Add:
/var/www/legal-analyzer/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    create 0644 www-data www-data
}
```

---

## Troubleshooting

### Common Issues

#### 1. "Permission Denied" Errors

```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/legal-analyzer

# Fix permissions
sudo chmod -R 755 /var/www/legal-analyzer
sudo chmod 777 /var/www/legal-analyzer/{logs,cache,tmp}
```

#### 2. "PHP Module Not Found"

```bash
# Check installed modules
php -m

# Install missing module (example: mbstring)
sudo apt-get install php7.4-mbstring
sudo systemctl restart apache2  # or php7.4-fpm for Nginx
```

#### 3. "500 Internal Server Error"

```bash
# Check PHP error log
tail -f /var/www/legal-analyzer/logs/php_errors.log

# Check web server error log
tail -f /var/log/apache2/error.log  # Apache
tail -f /var/log/nginx/error.log    # Nginx

# Enable error display temporarily (development only!)
# Edit .env: PHP_DISPLAY_ERRORS=On
```

#### 4. "Cache Not Working"

```bash
# Verify cache directory is writable
ls -la /var/www/legal-analyzer/cache

# Clear cache
rm -rf /var/www/legal-analyzer/cache/*

# Check opcache status (create info.php)
echo "<?php phpinfo(); ?>" > /var/www/legal-analyzer/info.php
# Visit: http://your-domain/info.php
# Remove after checking: rm /var/www/legal-analyzer/info.php
```

#### 5. "Session Errors"

```bash
# Check session directory
ls -la /var/lib/php/sessions

# Ensure PHP can write sessions
sudo chmod 1733 /var/lib/php/sessions
```

### Performance Issues

#### Slow Response Times

1. **Enable OPcache**:
```ini
# php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

2. **Enable APCu**:
```ini
# php.ini
apc.enabled=1
apc.shm_size=32M
```

3. **Check Cache Hit Rate**:
```bash
grep "Cache HIT\|Cache MISS" /var/www/legal-analyzer/logs/app.log | tail -100
```

Expected: >80% hit rate after warmup

---

## Rollback Procedure

### Automatic Rollback

```bash
# Rollback to previous deployment
./deploy.sh --rollback

# This will:
# 1. Show available backups
# 2. Ask for confirmation
# 3. Create safety backup of current state
# 4. Restore from latest backup
```

### Manual Rollback

```bash
# 1. List available backups
ls -lh /var/www/legal-analyzer/backups/

# 2. Extract specific backup
cd /var/www/legal-analyzer
tar -xzf backups/legal-analyzer_20231115_140322.tar.gz

# 3. Restart web server
sudo systemctl restart apache2  # or nginx
```

### Emergency Recovery

```bash
# If deployment script is broken:

# 1. Stop web server
sudo systemctl stop apache2

# 2. Move current installation
sudo mv /var/www/legal-analyzer /var/www/legal-analyzer.broken

# 3. Extract last known good backup
sudo mkdir /var/www/legal-analyzer
sudo tar -xzf /var/backups/legal-analyzer/backup_*.tar.gz \
    -C /var/www/legal-analyzer

# 4. Fix permissions
sudo chown -R www-data:www-data /var/www/legal-analyzer

# 5. Start web server
sudo systemctl start apache2
```

---

## Production Deployment Checklist

Before deploying to production:

- [ ] All tests passing (`php tests.php`)
- [ ] Knuth correctness tests passing
- [ ] Wolfram analysis tests passing
- [ ] SSL certificate installed
- [ ] Firewall configured
- [ ] Log rotation configured
- [ ] Automated backups scheduled
- [ ] Monitoring enabled
- [ ] Error reporting configured (email/Slack)
- [ ] `.env` file secured (chmod 600)
- [ ] Debug mode disabled
- [ ] Display errors disabled
- [ ] Cache enabled
- [ ] OPcache enabled
- [ ] Security headers configured
- [ ] Rate limiting verified
- [ ] CSRF protection verified
- [ ] Documentation updated
- [ ] Team notified of deployment

---

## Support & Resources

- **Documentation**: See all `*.md` files in repository
- **Issue Tracker**: https://github.com/your-org/legal-analyzer/issues
- **Security Issues**: security@your-domain.com
- **General Support**: support@your-domain.com

---

## Version History

- **v4.1** (2025-11-16): Knuth correctness fixes, mathematical rigor
- **v4.0** (2025-11-15): Wolfram computational enhancement
- **v3.0** (2025-11-14): Legal domain specialization
- **v2.0** (2025-11-13): Caching, logging, UI improvements
- **v1.0** (2025-11-12): Initial release

---

**End of Deployment Guide**

*For technical details, see KNUTH_ANALYSIS.md and WOLFRAM_ENHANCEMENT_DOCS.md*
