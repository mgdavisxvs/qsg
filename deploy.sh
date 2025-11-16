#!/bin/bash
set -euo pipefail  # Exit on error, undefined vars, pipe failures
IFS=$'\n\t'

###############################################################################
# Legal Document & Contract Analyzer - Deployment Script
# Version: 4.1 (Knuth · Wolfram · Torvalds)
#
# This script handles:
# - Prerequisites checking (PHP, extensions, permissions)
# - Environment setup (dev, staging, production)
# - Directory structure creation
# - Web server configuration (Apache/Nginx)
# - Security hardening
# - Health checks and validation
# - Rollback capability
#
# Usage:
#   ./deploy.sh [environment] [options]
#
# Environments:
#   dev         Development environment (debug enabled, no caching)
#   staging     Staging environment (production-like, test data)
#   production  Production environment (optimized, secure)
#
# Options:
#   --dry-run        Show what would be done without executing
#   --skip-tests     Skip test suite execution
#   --rollback       Rollback to previous deployment
#   --force          Force deployment even if checks fail
#   --help           Show this help message
#
# Author: Legal Analysis Team
# License: MIT
###############################################################################

# ============================================================================
# § 1. CONFIGURATION
# ============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_NAME="legal-analyzer"
VERSION="4.1"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Default values
ENVIRONMENT="${1:-production}"
DRY_RUN=false
SKIP_TESTS=false
FORCE=false
ROLLBACK=false

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# ============================================================================
# § 2. LOGGING FUNCTIONS
# ============================================================================

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

log_step() {
    echo ""
    echo -e "${BLUE}==>${NC} $1"
}

# ============================================================================
# § 3. ARGUMENT PARSING
# ============================================================================

parse_arguments() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --dry-run)
                DRY_RUN=true
                log_info "Dry-run mode enabled"
                shift
                ;;
            --skip-tests)
                SKIP_TESTS=true
                log_warning "Test execution will be skipped"
                shift
                ;;
            --rollback)
                ROLLBACK=true
                log_info "Rollback mode enabled"
                shift
                ;;
            --force)
                FORCE=true
                log_warning "Force mode enabled - skipping safety checks"
                shift
                ;;
            --help|-h)
                show_help
                exit 0
                ;;
            dev|staging|production)
                ENVIRONMENT=$1
                shift
                ;;
            *)
                log_error "Unknown argument: $1"
                show_help
                exit 1
                ;;
        esac
    done
}

show_help() {
    grep '^#' "$0" | grep -v '#!/bin/bash' | sed 's/^# //' | sed 's/^#//'
}

# ============================================================================
# § 4. PREREQUISITES CHECK
# ============================================================================

check_prerequisites() {
    log_step "Checking prerequisites..."

    local errors=0

    # Check if running as root (should NOT be)
    if [[ $EUID -eq 0 ]] && [[ "$FORCE" != true ]]; then
        log_error "This script should NOT be run as root for security reasons"
        log_info "Run as a regular user with sudo privileges if needed"
        ((errors++))
    fi

    # Check PHP version
    if ! command -v php &> /dev/null; then
        log_error "PHP is not installed"
        ((errors++))
    else
        local php_version=$(php -r 'echo PHP_VERSION;')
        local required_version="7.4"

        if ! php -r "exit(version_compare(PHP_VERSION, '$required_version', '>=') ? 0 : 1);"; then
            log_error "PHP version $php_version is too old (required: >= $required_version)"
            ((errors++))
        else
            log_success "PHP version: $php_version ✓"
        fi
    fi

    # Check required PHP extensions
    local required_extensions=(
        "mbstring"
        "json"
        "session"
        "pcre"
    )

    for ext in "${required_extensions[@]}"; do
        if ! php -m | grep -q "^${ext}$"; then
            log_error "Required PHP extension not found: $ext"
            ((errors++))
        else
            log_success "PHP extension $ext: ✓"
        fi
    done

    # Check optional but recommended extensions
    local optional_extensions=(
        "opcache"
        "apcu"
    )

    for ext in "${optional_extensions[@]}"; do
        if ! php -m | grep -q "^${ext}$"; then
            log_warning "Recommended PHP extension not found: $ext (performance may be impacted)"
        else
            log_success "PHP extension $ext: ✓"
        fi
    done

    # Check disk space (require at least 100MB free)
    local available_space=$(df -BM "$SCRIPT_DIR" | awk 'NR==2 {print $4}' | sed 's/M//')
    if [[ $available_space -lt 100 ]]; then
        log_error "Insufficient disk space: ${available_space}MB available (need 100MB)"
        ((errors++))
    else
        log_success "Disk space: ${available_space}MB available ✓"
    fi

    # Check write permissions
    if [[ ! -w "$SCRIPT_DIR" ]]; then
        log_error "No write permission in deployment directory: $SCRIPT_DIR"
        ((errors++))
    else
        log_success "Write permissions: ✓"
    fi

    if [[ $errors -gt 0 ]] && [[ "$FORCE" != true ]]; then
        log_error "Prerequisites check failed with $errors error(s)"
        log_info "Run with --force to bypass (not recommended)"
        exit 1
    fi

    log_success "All prerequisites satisfied"
}

# ============================================================================
# § 5. ENVIRONMENT CONFIGURATION
# ============================================================================

configure_environment() {
    log_step "Configuring environment: $ENVIRONMENT"

    case $ENVIRONMENT in
        dev)
            export PHP_ENV="development"
            export DEBUG_MODE="true"
            export CACHE_ENABLED="false"
            export LOG_LEVEL="debug"
            export ERROR_REPORTING="E_ALL"
            export DISPLAY_ERRORS="On"
            log_info "Development mode: Debug enabled, caching disabled"
            ;;
        staging)
            export PHP_ENV="staging"
            export DEBUG_MODE="false"
            export CACHE_ENABLED="true"
            export LOG_LEVEL="info"
            export ERROR_REPORTING="E_ALL"
            export DISPLAY_ERRORS="Off"
            log_info "Staging mode: Production-like with test data"
            ;;
        production)
            export PHP_ENV="production"
            export DEBUG_MODE="false"
            export CACHE_ENABLED="true"
            export LOG_LEVEL="warning"
            export ERROR_REPORTING="E_ALL & ~E_DEPRECATED & ~E_STRICT"
            export DISPLAY_ERRORS="Off"
            log_info "Production mode: Optimized and secured"
            ;;
        *)
            log_error "Invalid environment: $ENVIRONMENT"
            log_info "Valid environments: dev, staging, production"
            exit 1
            ;;
    esac
}

# ============================================================================
# § 6. DIRECTORY STRUCTURE SETUP
# ============================================================================

setup_directories() {
    log_step "Setting up directory structure..."

    local dirs=(
        "logs"
        "cache"
        "backups"
        "tmp"
    )

    for dir in "${dirs[@]}"; do
        local full_path="$SCRIPT_DIR/$dir"

        if [[ "$DRY_RUN" == true ]]; then
            log_info "[DRY-RUN] Would create directory: $full_path"
        else
            if [[ ! -d "$full_path" ]]; then
                mkdir -p "$full_path"
                log_success "Created directory: $dir"
            else
                log_info "Directory already exists: $dir"
            fi

            # Set permissions (755 for directories)
            chmod 755 "$full_path"

            # Make writable by web server for logs and cache
            if [[ "$dir" == "logs" ]] || [[ "$dir" == "cache" ]] || [[ "$dir" == "tmp" ]]; then
                chmod 777 "$full_path"
                log_info "Set writable permissions for: $dir"
            fi
        fi
    done

    log_success "Directory structure ready"
}

# ============================================================================
# § 7. FILE PERMISSIONS
# ============================================================================

set_file_permissions() {
    log_step "Setting file permissions..."

    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY-RUN] Would set file permissions"
        return
    fi

    # PHP files: 644 (readable by all, writable by owner)
    find "$SCRIPT_DIR" -type f -name "*.php" -exec chmod 644 {} \;
    log_success "PHP files: 644 (rw-r--r--)"

    # Make deployment script executable
    chmod 755 "$SCRIPT_DIR/deploy.sh"
    log_success "Deployment script: 755 (rwxr-xr-x)"

    # Documentation: 644
    find "$SCRIPT_DIR" -type f -name "*.md" -exec chmod 644 {} \;
    log_success "Documentation files: 644 (rw-r--r--)"

    # Test files: 644
    find "$SCRIPT_DIR" -type f -name "test*.php" -exec chmod 644 {} \;
    log_success "Test files: 644 (rw-r--r--)"

    # Sensitive files: 600 (only owner can read/write)
    if [[ -f "$SCRIPT_DIR/.env" ]]; then
        chmod 600 "$SCRIPT_DIR/.env"
        log_success "Environment file: 600 (rw-------)"
    fi

    log_success "File permissions configured"
}

# ============================================================================
# § 8. CONFIGURATION FILE GENERATION
# ============================================================================

generate_config() {
    log_step "Generating configuration files..."

    local env_file="$SCRIPT_DIR/.env"

    if [[ -f "$env_file" ]] && [[ "$FORCE" != true ]]; then
        log_info "Configuration file already exists: .env"
        log_info "Use --force to regenerate"
        return
    fi

    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY-RUN] Would generate .env file"
        return
    fi

    cat > "$env_file" <<EOF
# Legal Document Analyzer - Environment Configuration
# Generated: $TIMESTAMP
# Environment: $ENVIRONMENT

# Application
APP_ENV=$PHP_ENV
APP_DEBUG=$DEBUG_MODE
APP_VERSION=$VERSION

# Caching
CACHE_ENABLED=$CACHE_ENABLED
CACHE_MAX_SIZE=1000
CACHE_TTL=3600

# Logging
LOG_LEVEL=$LOG_LEVEL
LOG_FILE=logs/app.log
LOG_MAX_SIZE=10485760

# Security
CSRF_TOKEN_LENGTH=32
RATE_LIMIT_REQUESTS=10
RATE_LIMIT_WINDOW=60
SESSION_LIFETIME=3600

# PHP Settings
PHP_ERROR_REPORTING=$ERROR_REPORTING
PHP_DISPLAY_ERRORS=$DISPLAY_ERRORS
PHP_MAX_EXECUTION_TIME=30
PHP_MEMORY_LIMIT=256M

# Paths
BASE_PATH=$SCRIPT_DIR
LOGS_PATH=$SCRIPT_DIR/logs
CACHE_PATH=$SCRIPT_DIR/cache
TMP_PATH=$SCRIPT_DIR/tmp
EOF

    chmod 600 "$env_file"
    log_success "Configuration file generated: .env"
}

# ============================================================================
# § 9. WEB SERVER CONFIGURATION
# ============================================================================

configure_web_server() {
    log_step "Detecting web server..."

    local web_server=""

    # Detect Apache
    if command -v apache2 &> /dev/null || command -v httpd &> /dev/null; then
        web_server="apache"
        log_info "Detected: Apache"
    # Detect Nginx
    elif command -v nginx &> /dev/null; then
        web_server="nginx"
        log_info "Detected: Nginx"
    else
        log_warning "No web server detected (Apache/Nginx)"
        log_info "You'll need to configure your web server manually"
        return
    fi

    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY-RUN] Would generate $web_server configuration"
        return
    fi

    case $web_server in
        apache)
            generate_apache_config
            ;;
        nginx)
            generate_nginx_config
            ;;
    esac
}

generate_apache_config() {
    log_info "Generating Apache configuration..."

    local config_file="$SCRIPT_DIR/.htaccess"

    cat > "$config_file" <<'EOF'
# Legal Document Analyzer - Apache Configuration

# Disable directory listing
Options -Indexes

# Enable following symbolic links
Options +FollowSymLinks

# Enable mod_rewrite
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Force HTTPS (production only)
    # RewriteCond %{HTTPS} off
    # RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]

    # Block access to sensitive files
    RewriteRule ^\.env$ - [F,L]
    RewriteRule ^\.git/ - [F,L]
    RewriteRule ^logs/ - [F,L]
    RewriteRule ^cache/ - [F,L]
    RewriteRule ^backups/ - [F,L]
    RewriteRule ^tmp/ - [F,L]
</IfModule>

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    Header set Content-Security-Policy "default-src 'self' https://cdn.tailwindcss.com; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com;"
</IfModule>

# PHP settings
<IfModule mod_php7.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 10M
    php_value max_execution_time 30
    php_value max_input_time 30
    php_value memory_limit 256M
    php_flag display_errors off
    php_flag log_errors on
    php_value error_log logs/php_errors.log
</IfModule>

# Block access to backup files
<FilesMatch "\.(bak|backup|old|save|swp|tmp)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Block access to hidden files
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>
EOF

    log_success "Apache configuration created: .htaccess"
}

generate_nginx_config() {
    log_info "Generating Nginx configuration..."

    local config_file="$SCRIPT_DIR/nginx.conf"

    cat > "$config_file" <<EOF
# Legal Document Analyzer - Nginx Configuration
# Place this in /etc/nginx/sites-available/ and symlink to sites-enabled

server {
    listen 80;
    server_name legal-analyzer.example.com;

    root $SCRIPT_DIR;
    index qsgx_v2.php;

    # Logging
    access_log $SCRIPT_DIR/logs/nginx_access.log;
    error_log $SCRIPT_DIR/logs/nginx_error.log;

    # Security headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Block access to sensitive files/directories
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
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;

        # PHP settings
        fastcgi_param PHP_VALUE "
            upload_max_filesize=10M
            post_max_size=10M
            max_execution_time=30
            memory_limit=256M
            display_errors=off
            log_errors=on
            error_log=$SCRIPT_DIR/logs/php_errors.log
        ";
    }

    # Block access to backup files
    location ~ \.(bak|backup|old|save|swp|tmp)$ {
        deny all;
        access_log off;
    }

    # Force HTTPS (uncomment for production)
    # if (\$scheme != "https") {
    #     return 301 https://\$server_name\$request_uri;
    # }
}
EOF

    log_success "Nginx configuration created: nginx.conf"
    log_info "Copy nginx.conf to /etc/nginx/sites-available/ and enable it"
}

# ============================================================================
# § 10. BACKUP CREATION
# ============================================================================

create_backup() {
    log_step "Creating backup of current deployment..."

    local backup_dir="$SCRIPT_DIR/backups"
    local backup_file="$backup_dir/${APP_NAME}_${TIMESTAMP}.tar.gz"

    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY-RUN] Would create backup: $backup_file"
        return
    fi

    # Create backup of all PHP files and configs
    tar -czf "$backup_file" \
        --exclude='backups' \
        --exclude='cache/*' \
        --exclude='logs/*' \
        --exclude='tmp/*' \
        --exclude='.git' \
        -C "$SCRIPT_DIR" \
        $(ls "$SCRIPT_DIR" | grep -v "^backups$\|^cache$\|^logs$\|^tmp$\|^\.git$") \
        2>/dev/null || true

    if [[ -f "$backup_file" ]]; then
        local backup_size=$(du -h "$backup_file" | cut -f1)
        log_success "Backup created: $(basename "$backup_file") ($backup_size)"

        # Keep only last 5 backups
        local backup_count=$(ls -1 "$backup_dir"/*.tar.gz 2>/dev/null | wc -l)
        if [[ $backup_count -gt 5 ]]; then
            log_info "Removing old backups (keeping last 5)..."
            ls -t "$backup_dir"/*.tar.gz | tail -n +6 | xargs rm -f
        fi
    else
        log_warning "Backup creation failed (non-critical)"
    fi
}

# ============================================================================
# § 11. TEST EXECUTION
# ============================================================================

run_tests() {
    if [[ "$SKIP_TESTS" == true ]]; then
        log_warning "Skipping test execution (--skip-tests flag)"
        return
    fi

    log_step "Running test suite..."

    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY-RUN] Would run test suite"
        return
    fi

    # Run main test suite
    if [[ -f "$SCRIPT_DIR/tests.php" ]]; then
        log_info "Running tests.php..."
        if php "$SCRIPT_DIR/tests.php" > "$SCRIPT_DIR/tmp/test_results.txt" 2>&1; then
            local passed=$(grep -c "✓" "$SCRIPT_DIR/tmp/test_results.txt" || echo "0")
            local failed=$(grep -c "✗" "$SCRIPT_DIR/tmp/test_results.txt" || echo "0")

            if [[ $failed -eq 0 ]]; then
                log_success "All tests passed ($passed tests)"
            else
                log_error "Tests failed: $failed failures"
                cat "$SCRIPT_DIR/tmp/test_results.txt"

                if [[ "$FORCE" != true ]]; then
                    exit 1
                fi
            fi
        else
            log_error "Test execution failed"
            cat "$SCRIPT_DIR/tmp/test_results.txt"

            if [[ "$FORCE" != true ]]; then
                exit 1
            fi
        fi
    else
        log_warning "Test file not found: tests.php"
    fi

    # Run Knuth correctness tests
    if [[ -f "$SCRIPT_DIR/test_knuth_fixes.php" ]]; then
        log_info "Running Knuth correctness tests..."
        if php "$SCRIPT_DIR/test_knuth_fixes.php" > "$SCRIPT_DIR/tmp/knuth_test_results.txt" 2>&1; then
            local pass_count=$(grep -c "✓ PASS" "$SCRIPT_DIR/tmp/knuth_test_results.txt" || echo "0")
            log_success "Knuth correctness tests passed ($pass_count checks)"
        else
            log_warning "Knuth tests had issues (non-critical)"
        fi
    fi

    # Run Wolfram analysis tests
    if [[ -f "$SCRIPT_DIR/test_wolfram.php" ]]; then
        log_info "Running Wolfram analysis tests..."
        if php "$SCRIPT_DIR/test_wolfram.php" > "$SCRIPT_DIR/tmp/wolfram_test_results.txt" 2>&1; then
            local wolfram_passed=$(grep -c "✓" "$SCRIPT_DIR/tmp/wolfram_test_results.txt" || echo "0")
            log_success "Wolfram analysis tests passed ($wolfram_passed checks)"
        else
            log_warning "Wolfram tests had issues (non-critical)"
        fi
    fi
}

# ============================================================================
# § 12. HEALTH CHECK
# ============================================================================

perform_health_check() {
    log_step "Performing health check..."

    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY-RUN] Would perform health check"
        return
    fi

    local errors=0

    # Check all required PHP files exist
    local required_files=(
        "qsgx_v2.php"
        "config.php"
        "security.php"
        "analysis_core.php"
        "legal_analysis.php"
        "wolfram_analysis.php"
        "cache.php"
        "logger.php"
    )

    for file in "${required_files[@]}"; do
        if [[ ! -f "$SCRIPT_DIR/$file" ]]; then
            log_error "Required file missing: $file"
            ((errors++))
        fi
    done

    # Check PHP syntax for all PHP files
    log_info "Checking PHP syntax..."
    local syntax_errors=0

    for file in "$SCRIPT_DIR"/*.php; do
        if [[ -f "$file" ]]; then
            if ! php -l "$file" > /dev/null 2>&1; then
                log_error "Syntax error in: $(basename "$file")"
                ((syntax_errors++))
            fi
        fi
    done

    if [[ $syntax_errors -eq 0 ]]; then
        log_success "All PHP files have valid syntax"
    else
        log_error "Found $syntax_errors syntax errors"
        ((errors++))
    fi

    # Check writable directories
    local writable_dirs=("logs" "cache" "tmp")

    for dir in "${writable_dirs[@]}"; do
        local dir_path="$SCRIPT_DIR/$dir"
        if [[ ! -w "$dir_path" ]]; then
            log_error "Directory not writable: $dir"
            ((errors++))
        fi
    done

    # Test log writing
    if ! echo "Health check: $TIMESTAMP" >> "$SCRIPT_DIR/logs/deployment.log" 2>/dev/null; then
        log_error "Cannot write to logs/deployment.log"
        ((errors++))
    fi

    # Test cache directory
    local test_cache_file="$SCRIPT_DIR/cache/.test_$TIMESTAMP"
    if ! touch "$test_cache_file" 2>/dev/null; then
        log_error "Cannot write to cache directory"
        ((errors++))
    else
        rm -f "$test_cache_file"
    fi

    if [[ $errors -eq 0 ]]; then
        log_success "Health check passed: All systems operational ✓"
    else
        log_error "Health check failed with $errors error(s)"

        if [[ "$FORCE" != true ]]; then
            exit 1
        fi
    fi
}

# ============================================================================
# § 13. ROLLBACK FUNCTIONALITY
# ============================================================================

perform_rollback() {
    log_step "Performing rollback..."

    local backup_dir="$SCRIPT_DIR/backups"
    local latest_backup=$(ls -t "$backup_dir"/*.tar.gz 2>/dev/null | head -1)

    if [[ -z "$latest_backup" ]]; then
        log_error "No backup found for rollback"
        exit 1
    fi

    log_info "Found backup: $(basename "$latest_backup")"

    if [[ "$DRY_RUN" == true ]]; then
        log_info "[DRY-RUN] Would restore from: $(basename "$latest_backup")"
        return
    fi

    read -p "Are you sure you want to rollback? (yes/no): " -r
    if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        log_info "Rollback cancelled"
        exit 0
    fi

    # Create a backup of current state before rollback
    log_info "Creating safety backup before rollback..."
    create_backup

    # Extract backup
    log_info "Restoring from backup..."
    tar -xzf "$latest_backup" -C "$SCRIPT_DIR"

    log_success "Rollback completed successfully"
    log_info "Previous state restored from: $(basename "$latest_backup")"
}

# ============================================================================
# § 14. DEPLOYMENT SUMMARY
# ============================================================================

print_summary() {
    log_step "Deployment Summary"

    echo ""
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║  Legal Document & Contract Analyzer v$VERSION"
    echo "║  Deployment: $ENVIRONMENT"
    echo "║  Timestamp: $TIMESTAMP"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""
    echo "Application Path: $SCRIPT_DIR"
    echo "PHP Version:      $(php -r 'echo PHP_VERSION;')"
    echo "Environment:      $ENVIRONMENT"
    echo "Debug Mode:       $DEBUG_MODE"
    echo "Cache Enabled:    $CACHE_ENABLED"
    echo "Log Level:        $LOG_LEVEL"
    echo ""
    echo "Directories:"
    echo "  - Logs:         $SCRIPT_DIR/logs"
    echo "  - Cache:        $SCRIPT_DIR/cache"
    echo "  - Backups:      $SCRIPT_DIR/backups"
    echo ""
    echo "Main Application: $SCRIPT_DIR/qsgx_v2.php"
    echo ""

    if [[ "$DRY_RUN" == true ]]; then
        echo "⚠️  DRY-RUN MODE - No changes were made"
    else
        echo "✅ Deployment completed successfully!"
    fi

    echo ""
    echo "Next Steps:"
    echo "  1. Configure your web server to point to: $SCRIPT_DIR"
    echo "  2. Set up SSL certificate (recommended for production)"
    echo "  3. Review logs in: $SCRIPT_DIR/logs/"
    echo "  4. Access application at: http://your-domain/qsgx_v2.php"
    echo ""

    if [[ "$ENVIRONMENT" == "production" ]]; then
        echo "⚠️  PRODUCTION DEPLOYMENT CHECKLIST:"
        echo "  [ ] SSL certificate installed"
        echo "  [ ] Firewall configured"
        echo "  [ ] Backups scheduled"
        echo "  [ ] Monitoring enabled"
        echo "  [ ] Error reporting configured"
        echo ""
    fi
}

# ============================================================================
# § 15. CLEANUP
# ============================================================================

cleanup() {
    log_info "Cleaning up temporary files..."

    if [[ -d "$SCRIPT_DIR/tmp" ]]; then
        find "$SCRIPT_DIR/tmp" -type f -name "*.txt" -mtime +7 -delete 2>/dev/null || true
        log_success "Temporary files cleaned"
    fi
}

# ============================================================================
# § 16. MAIN DEPLOYMENT FLOW
# ============================================================================

main() {
    echo ""
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║                                                            ║"
    echo "║  Legal Document & Contract Analyzer                        ║"
    echo "║  Deployment Script v$VERSION                                    ║"
    echo "║                                                            ║"
    echo "║  Knuth · Wolfram · Torvalds                                ║"
    echo "║                                                            ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo ""

    # Parse command line arguments
    parse_arguments "$@"

    # Handle rollback
    if [[ "$ROLLBACK" == true ]]; then
        perform_rollback
        exit 0
    fi

    # Main deployment flow
    check_prerequisites
    configure_environment
    create_backup
    setup_directories
    set_file_permissions
    generate_config
    configure_web_server
    run_tests
    perform_health_check
    cleanup
    print_summary

    # Log deployment
    echo "$TIMESTAMP - Deployment successful ($ENVIRONMENT)" >> "$SCRIPT_DIR/logs/deployment.log"
}

# ============================================================================
# § 17. EXECUTION
# ============================================================================

# Trap errors and cleanup
trap 'log_error "Deployment failed at line $LINENO"; exit 1' ERR

# Run main function
main "$@"

exit 0
