<?php
declare(strict_types=1);

/**
 * QSG Ruliad Console - Security Utilities
 *
 * LINUS TORVALDS: "Security is not optional. Fail securely or don't ship."
 *
 * This module provides:
 * - CSRF protection
 * - Session security (fixation prevention, timeouts)
 * - Input validation
 * - Rate limiting
 *
 * @author  QSG Ruliad Team (Torvalds edition)
 */

require_once __DIR__ . '/config.php';

// ============================================================================
// SECURE SESSION INITIALIZATION
// ============================================================================

/**
 * Initialize secure session with hardened settings.
 *
 * TORVALDS: "Use strict mode or get pwned by session fixation."
 */
function init_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return; // Already started
    }

    session_start([
        'cookie_httponly' => true,           // Prevent JS access to session cookie
        'cookie_secure'   => true,           // HTTPS only (set to false for dev)
        'cookie_samesite' => 'Strict',       // CSRF mitigation
        'use_strict_mode' => true,           // Reject uninitialized session IDs
        'use_only_cookies' => true,          // No session ID in URLs
    ]);

    // Regenerate session ID on first use (prevents fixation)
    if (!isset($_SESSION['initialized'])) {
        session_regenerate_id(true);
        $_SESSION['initialized'] = true;
        $_SESSION['created'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
    }

    // Session timeout check
    if (isset($_SESSION['created']) && (time() - $_SESSION['created'] > SESSION_TIMEOUT_SECONDS)) {
        session_unset();
        session_destroy();
        session_start([
            'cookie_httponly' => true,
            'cookie_secure'   => true,
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
        ]);
        http_response_code(401);
        die(json_encode(['ok' => false, 'error' => 'Session expired']));
    }

    // Session hijacking prevention (basic IP check)
    $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $current_ip) {
        // TORVALDS: "Fail loudly when security assumptions break"
        session_unset();
        session_destroy();
        http_response_code(403);
        die(json_encode(['ok' => false, 'error' => 'Security violation detected']));
    }

    // Refresh activity timestamp
    $_SESSION['last_activity'] = time();
}

// ============================================================================
// CSRF PROTECTION
// ============================================================================

/**
 * Generate CSRF token for form protection.
 *
 * TORVALDS: "No CSRF protection = your POST endpoints are public APIs."
 */
function generate_csrf_token(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from request.
 *
 * @param string $token Token from POST request
 * @return bool True if valid
 */
function verify_csrf_token(?string $token): bool {
    if (!isset($_SESSION['csrf_token']) || $token === null) {
        return false;
    }
    // Timing-safe comparison
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token or die.
 */
function require_csrf_token(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verify_csrf_token($token)) {
        http_response_code(403);
        die(json_encode(['ok' => false, 'error' => 'CSRF token validation failed']));
    }
}

// ============================================================================
// INPUT VALIDATION
// ============================================================================

/**
 * Validate and sanitize clause input.
 *
 * TORVALDS: "Validate input or enjoy your RCE."
 *
 * @param string $clause Raw clause input
 * @return string Sanitized clause
 * @throws InvalidArgumentException
 */
function validate_clause(string $clause): string {
    // Length check (DoS prevention)
    if (strlen($clause) > MAX_CLAUSE_LENGTH) {
        throw new InvalidArgumentException(
            sprintf('Clause too long (max %d characters)', MAX_CLAUSE_LENGTH)
        );
    }

    if (strlen($clause) === 0) {
        throw new InvalidArgumentException('Clause cannot be empty');
    }

    // Remove control characters (except newlines/tabs)
    $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clause);

    if ($sanitized === null) {
        throw new InvalidArgumentException('Invalid UTF-8 in clause');
    }

    return $sanitized;
}

/**
 * Validate BPM input.
 */
function validate_bpm(string $bpm): int {
    $value = filter_var($bpm, FILTER_VALIDATE_INT);
    if ($value === false || $value < 30 || $value > 240) {
        throw new InvalidArgumentException('BPM must be between 30 and 240');
    }
    return $value;
}

// ============================================================================
// RATE LIMITING
// ============================================================================

/**
 * Check rate limit for current session.
 *
 * TORVALDS: "Rate limiting: because computational DoS is trivial."
 *
 * @return bool True if within limit, false if exceeded
 */
function check_rate_limit(): bool {
    if (!isset($_SESSION['requests'])) {
        $_SESSION['requests'] = [];
    }

    $now = time();

    // Clean old requests outside window
    $_SESSION['requests'] = array_filter(
        $_SESSION['requests'],
        fn($timestamp) => ($now - $timestamp) < RATE_LIMIT_WINDOW_SECONDS
    );

    // Check limit
    if (count($_SESSION['requests']) >= RATE_LIMIT_MAX_REQUESTS) {
        return false;
    }

    // Record this request
    $_SESSION['requests'][] = $now;
    return true;
}

/**
 * Require rate limit compliance or die.
 */
function require_rate_limit(): void {
    if (!check_rate_limit()) {
        http_response_code(429); // Too Many Requests
        die(json_encode([
            'ok' => false,
            'error' => sprintf(
                'Rate limit exceeded (%d requests per %d seconds)',
                RATE_LIMIT_MAX_REQUESTS,
                RATE_LIMIT_WINDOW_SECONDS
            )
        ]));
    }
}

// ============================================================================
// SECURE OUTPUT
// ============================================================================

/**
 * HTML escape for safe output.
 *
 * @param mixed $value Value to escape
 * @return string Escaped string
 */
function h($value): string {
    if ($value === null) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ============================================================================
// ERROR HANDLER (Torvalds: "Fail loudly")
// ============================================================================

/**
 * Set up error handler that converts errors to exceptions.
 */
function setup_error_handler(): void {
    set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) {
        // Don't throw exceptions for suppressed errors (@-operator)
        if (!(error_reporting() & $errno)) {
            return false;
        }

        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    });
}

/**
 * Set up exception handler for uncaught exceptions.
 */
function setup_exception_handler(): void {
    set_exception_handler(function (Throwable $e) {
        error_log(sprintf(
            'Uncaught %s: %s in %s:%d',
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        http_response_code(500);
        if (isset($_POST['mode']) && $_POST['mode'] === 'analyze_json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'error' => 'Internal server error. Please try again.',
                // In production, don't expose error details:
                // 'debug' => $e->getMessage()
            ]);
        } else {
            echo '<!DOCTYPE html><html><body><h1>Internal Server Error</h1></body></html>';
        }
        exit(1);
    });
}
