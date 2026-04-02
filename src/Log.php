<?php

declare(strict_types=1);

namespace WicketWP;

// No direct access
defined('ABSPATH') || exit;

/**
 * Centralized logging for the Wicket plugin stack.
 *
 * File-based, daily-rotating logs stored in wp-content/uploads/wc-logs/
 * when WooCommerce is active, otherwise wp-content/uploads/wicket-logs/.
 * Filename: wicket-{source}-{Y-m-d}-{hash}.log
 *
 * CRITICAL and ERROR are always written; all other levels require WP_DEBUG.
 */
class Log
{
    public const LOG_LEVEL_DEBUG = 'debug';
    public const LOG_LEVEL_INFO = 'info';
    public const LOG_LEVEL_WARNING = 'warning';
    public const LOG_LEVEL_ERROR = 'error';
    public const LOG_LEVEL_CRITICAL = 'critical';

    private static bool $logDirSetupDone = false;
    private static ?string $logBaseDir = null;

    /**
     * Registers a shutdown handler to catch and log fatal PHP errors.
     * Call once during early plugin bootstrap (before plugins_loaded).
     */
    public static function registerFatalErrorHandler(): void
    {
        register_shutdown_function([new self(), 'handleFatalError']);
    }

    /**
     * Shutdown callback — do not call directly.
     *
     * Skips logging when WooCommerce's logger is available: WC registers its own
     * shutdown handler and would produce a duplicate entry for the same fatal error.
     */
    public function handleFatalError(): void
    {
        // WC_Logger being present means WooCommerce loaded successfully and its
        // shutdown handler will already capture this fatal error.
        if (class_exists('WC_Logger')) {
            return;
        }

        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            $message = sprintf(
                'Fatal Error: %s in %s on line %d',
                $error['message'],
                $error['file'],
                $error['line']
            );

            $this->log(self::LOG_LEVEL_CRITICAL, $message, ['source' => 'wicket-fatal-error']);
        }
    }

    /**
     * Write a log entry.
     *
     * @param string $level   One of the LOG_LEVEL_* constants.
     * @param string $message Human-readable message.
     * @param array  $context Arbitrary context. Use 'source' key to group log files.
     * @return bool True on success, false if the entry could not be written.
     */
    public function log(string $level, string $message, array $context = []): bool
    {
        // Always log CRITICAL and ERROR. All other levels require WP_DEBUG.
        if ($level !== self::LOG_LEVEL_CRITICAL && $level !== self::LOG_LEVEL_ERROR) {
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                return true;
            }
        }

        if (!self::$logDirSetupDone) {
            if (!$this->setupLogDirectory()) {
                error_log("Wicket Log Directory Setup Failed. Original log: [{$level}] {$message}");

                return false;
            }
            self::$logDirSetupDone = true;
        }

        $source = sanitize_file_name($context['source'] ?? 'wicket-plugin');
        if (empty($source)) {
            $source = 'wicket-plugin';
        }

        $date_suffix = date('Y-m-d');
        $file_hash = wp_hash($source);
        $filename = "wicket-{$source}-{$date_suffix}-{$file_hash}.log";
        $log_file_path = self::$logBaseDir . $filename;

        $timestamp = date('Y-m-d\\TH:i:s\\Z'); // ISO 8601 UTC
        $formatted_level = strtoupper($level);
        $context_payload = '';
        if (!empty($context)) {
            $context_payload = ' ' . wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $log_entry = "{$timestamp} [{$formatted_level}]: {$message}{$context_payload}" . PHP_EOL;

        if (!error_log($log_entry, 3, $log_file_path)) {
            error_log("Wicket Log File Write Failed to {$log_file_path}. Original log: [{$level}] {$message}");

            return false;
        }

        return true;
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_CRITICAL, $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_ERROR, $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_WARNING, $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_INFO, $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LOG_LEVEL_DEBUG, $message, $context);
    }

    /**
     * Ensures the log directory exists and is web-inaccessible.
     */
    private function setupLogDirectory(): bool
    {
        if (self::$logBaseDir === null) {
            $upload_dir = wp_upload_dir();
            if (!empty($upload_dir['error'])) {
                error_log('Wicket Log Error: Could not get WordPress upload directory. ' . $upload_dir['error']);

                return false;
            }

            $log_subdir = class_exists('WooCommerce') ? 'wc-logs' : 'wicket-logs';
            self::$logBaseDir = $upload_dir['basedir'] . '/' . $log_subdir . '/';
        }

        if (!is_dir(self::$logBaseDir)) {
            if (!wp_mkdir_p(self::$logBaseDir)) {
                error_log('Wicket Log Error: Could not create log directory: ' . self::$logBaseDir);

                return false;
            }
        }

        $htaccess_file = self::$logBaseDir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = 'deny from all' . PHP_EOL . 'Require all denied' . PHP_EOL;
            if (@file_put_contents($htaccess_file, $htaccess_content) === false) {
                error_log('Wicket Log Error: Could not create .htaccess file in ' . self::$logBaseDir);
            }
        }

        $index_html_file = self::$logBaseDir . 'index.html';
        if (!file_exists($index_html_file)) {
            if (@file_put_contents($index_html_file, '<!-- Silence is golden. -->') === false) {
                error_log('Wicket Log Error: Could not create index.html file in ' . self::$logBaseDir);
            }
        }

        return true;
    }
}
