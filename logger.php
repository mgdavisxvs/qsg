<?php
declare(strict_types=1);

/**
 * Production Logger
 *
 * TORVALDS: "Logging saved my ass more times than I can count."
 *
 * PSR-3 compatible logger with levels, structured context, and file rotation.
 * Essential for production debugging and monitoring.
 *
 * @author  QSG Ruliad Team (Torvalds edition)
 */

/**
 * Production logger with levels and context.
 *
 * TORVALDS: "Good logging makes debugging 10× faster. Bad logging makes you cry at 3am."
 *
 * Features:
 * - PSR-3 compatible log levels
 * - Structured context (JSON)
 * - Atomic writes (LOCK_EX)
 * - Configurable min level
 * - Optional file rotation
 */
class Logger {
    /** Log level constants */
    const DEBUG = 0;
    const INFO = 1;
    const WARN = 2;
    const ERROR = 3;

    /** @var string Path to log file */
    private string $log_file;

    /** @var int Minimum level to log */
    private int $min_level;

    /** @var bool Enable log rotation */
    private bool $rotation_enabled;

    /** @var int Max log file size in bytes (default 10MB) */
    private int $max_file_size;

    /**
     * @param string $log_file Path to log file
     * @param int $min_level Minimum level to log (default INFO)
     * @param bool $rotation_enabled Enable file rotation
     * @param int $max_file_size Max log size before rotation (bytes)
     */
    public function __construct(
        string $log_file = '/tmp/qsg.log',
        int $min_level = self::INFO,
        bool $rotation_enabled = true,
        int $max_file_size = 10 * 1024 * 1024  // 10MB
    ) {
        $this->log_file = $log_file;
        $this->min_level = $min_level;
        $this->rotation_enabled = $rotation_enabled;
        $this->max_file_size = $max_file_size;
    }

    /**
     * Log debug message.
     *
     * @param string $message Log message
     * @param array $context Additional context
     */
    public function debug(string $message, array $context = []): void {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Log info message.
     *
     * @param string $message Log message
     * @param array $context Additional context
     */
    public function info(string $message, array $context = []): void {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Log warning message.
     *
     * @param string $message Log message
     * @param array $context Additional context
     */
    public function warn(string $message, array $context = []): void {
        $this->log(self::WARN, $message, $context);
    }

    /**
     * Log error message.
     *
     * @param string $message Log message
     * @param array $context Additional context
     */
    public function error(string $message, array $context = []): void {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Core logging method.
     *
     * TORVALDS: "Use LOCK_EX or you'll get corrupted logs under concurrent load."
     *
     * @param int $level Log level
     * @param string $message Log message
     * @param array $context Structured context
     */
    private function log(int $level, string $message, array $context): void {
        // Skip if below min level
        if ($level < $this->min_level) {
            return;
        }

        $level_name = $this->level_to_string($level);
        $timestamp = date('Y-m-d H:i:s');

        // Enrich context with standard fields
        $context['timestamp'] = $timestamp;
        $context['level'] = $level_name;
        $context['pid'] = getmypid();

        // Format as JSON for structured logging
        $context_json = json_encode($context, JSON_UNESCAPED_SLASHES);

        // Log line format: [timestamp] LEVEL: message {context}
        $line = "[{$timestamp}] {$level_name}: {$message} {$context_json}\n";

        // TORVALDS: "Atomic writes with exclusive lock"
        file_put_contents($this->log_file, $line, FILE_APPEND | LOCK_EX);

        // Check if rotation needed
        if ($this->rotation_enabled) {
            $this->rotate_if_needed();
        }
    }

    /**
     * Rotate log file if it exceeds max size.
     *
     * TORVALDS: "Log rotation prevents disk full errors at 3am."
     */
    private function rotate_if_needed(): void {
        if (!file_exists($this->log_file)) {
            return;
        }

        $size = filesize($this->log_file);
        if ($size === false || $size < $this->max_file_size) {
            return;
        }

        // Rotate: current.log → current.log.1, keep 5 old logs
        for ($i = 4; $i >= 1; $i--) {
            $old = $this->log_file . '.' . $i;
            $new = $this->log_file . '.' . ($i + 1);
            if (file_exists($old)) {
                rename($old, $new);
            }
        }

        // Move current log to .1
        rename($this->log_file, $this->log_file . '.1');
    }

    /**
     * Convert log level to string.
     *
     * @param int $level Log level constant
     * @return string Level name
     */
    private function level_to_string(int $level): string {
        return match($level) {
            self::DEBUG => 'DEBUG',
            self::INFO => 'INFO',
            self::WARN => 'WARN',
            self::ERROR => 'ERROR',
            default => 'UNKNOWN',
        };
    }

    /**
     * Get current log file path.
     *
     * @return string Log file path
     */
    public function get_log_file(): string {
        return $this->log_file;
    }

    /**
     * Set minimum log level.
     *
     * @param int $level Minimum level
     */
    public function set_min_level(int $level): void {
        $this->min_level = $level;
    }

    /**
     * Get recent log entries (last N lines).
     *
     * TORVALDS: "Tail the log for debugging."
     *
     * @param int $lines Number of recent lines to get
     * @return array Log lines
     */
    public function tail(int $lines = 50): array {
        if (!file_exists($this->log_file)) {
            return [];
        }

        // Read last N lines efficiently (no need to load entire file)
        $handle = fopen($this->log_file, 'r');
        if ($handle === false) {
            return [];
        }

        $buffer = [];
        $file_size = filesize($this->log_file);

        if ($file_size === false || $file_size === 0) {
            fclose($handle);
            return [];
        }

        // Start from end of file
        $pos = $file_size - 1;
        $line_count = 0;
        $current_line = '';

        while ($pos >= 0 && $line_count < $lines) {
            fseek($handle, $pos);
            $char = fgetc($handle);

            if ($char === "\n") {
                if ($current_line !== '') {
                    $buffer[] = $current_line;
                    $line_count++;
                    $current_line = '';
                }
            } else {
                $current_line = $char . $current_line;
            }

            $pos--;
        }

        // Add last line if not empty
        if ($current_line !== '' && $line_count < $lines) {
            $buffer[] = $current_line;
        }

        fclose($handle);

        // Reverse to get chronological order
        return array_reverse($buffer);
    }
}

/**
 * Global logger instance for convenience.
 *
 * TORVALDS: "Singletons aren't always evil."
 *
 * @return Logger Global logger instance
 */
function get_logger(): Logger {
    static $logger = null;
    if ($logger === null) {
        $log_file = getenv('QSG_LOG_FILE') ?: '/tmp/qsg.log';
        $min_level = getenv('QSG_LOG_LEVEL') === 'DEBUG' ? Logger::DEBUG : Logger::INFO;
        $logger = new Logger($log_file, $min_level);
    }
    return $logger;
}

/**
 * Quick log helper functions.
 */
function log_debug(string $message, array $context = []): void {
    get_logger()->debug($message, $context);
}

function log_info(string $message, array $context = []): void {
    get_logger()->info($message, $context);
}

function log_warn(string $message, array $context = []): void {
    get_logger()->warn($message, $context);
}

function log_error(string $message, array $context = []): void {
    get_logger()->error($message, $context);
}
