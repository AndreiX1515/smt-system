<?php

/**
 * error_logger.php
 * General-purpose error logging utility.
 *
 * Usage:
 *   require_once __DIR__ . '/path/to/error_logger.php';
 *   AppLogger::log("Something went wrong", "layout.php");
 *   AppLogger::warn("Missing optional asset", "layout.php");
 *   AppLogger::info("Page loaded", "layout.php");
 *
 * Config:
 *   - LOG_FILE_PATH  : define() before including this file to override log path
 *   - LOG_LEVEL      : 'debug' | 'info' | 'warn' | 'error' (default: 'debug')
 */

// ── Configuration ────────────────────────────────────────────────────────────
if (!defined('LOG_FILE_PATH')) {
    define('LOG_FILE_PATH', __DIR__ . '/logs/app_errors.log');
}

if (!defined('LOG_LEVEL')) {
    define('LOG_LEVEL', 'debug'); // Log everything by default
}

// ── Logger Class ─────────────────────────────────────────────────────────────
class AppLogger
{
    /** Severity levels and their numeric weights */
    private const LEVELS = [
        'debug' => 0,
        'info'  => 1,
        'warn'  => 2,
        'error' => 3,
    ];

    /**
     * Write to both the custom log file and PHP's default error log.
     *
     * @param string $message   Human-readable description
     * @param string $source    File or context where the issue occurred
     * @param string $level     'debug' | 'info' | 'warn' | 'error'
     * @param array  $context   Optional extra data (e.g. ['path' => $path])
     */
    public static function log(
        string $message,
        string $source  = 'app',
        string $level   = 'error',
        array  $context = []
    ): void {
        // ── Level filter ─────────────────────────────────────────────────────
        $minLevel     = strtolower(LOG_LEVEL);
        $currentLevel = strtolower($level);

        $minWeight     = self::LEVELS[$minLevel]     ?? 0;
        $currentWeight = self::LEVELS[$currentLevel] ?? 3;

        if ($currentWeight < $minWeight) {
            return; // Below threshold, skip
        }

        // ── Build log entry ──────────────────────────────────────────────────
        $timestamp  = date('Y-m-d H:i:s');
        $levelTag   = strtoupper($level);
        $contextStr = !empty($context)
            ? ' | context: ' . json_encode(self::sanitizeContext($context), JSON_UNESCAPED_SLASHES)
            : '';

        $entry = "[{$timestamp}] [{$levelTag}] [{$source}] {$message}{$contextStr}" . PHP_EOL;

        // ── Write to custom log file ─────────────────────────────────────────
        self::writeToFile($entry);

        // ── Write to PHP default error log ───────────────────────────────────
        error_log("[{$levelTag}] [{$source}] {$message}{$contextStr}");
    }

    // ── Convenience helpers ───────────────────────────────────────────────────

    public static function error(string $message, string $source = 'app', array $context = []): void
    {
        self::log($message, $source, 'error', $context);
    }

    public static function warn(string $message, string $source = 'app', array $context = []): void
    {
        self::log($message, $source, 'warn', $context);
    }

    public static function info(string $message, string $source = 'app', array $context = []): void
    {
        self::log($message, $source, 'info', $context);
    }

    public static function debug(string $message, string $source = 'app', array $context = []): void
    {
        self::log($message, $source, 'debug', $context);
    }


    
    // ── Context sanitizer ────────────────────────────────────────────────────
    /**
     * Truncate any data: URIs in context values to keep logs readable.
     * e.g. "data:text/javascript;base64,d2luZG93Ll…[base64]"
     */
    private static function sanitizeContext(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($value) && str_starts_with($value, 'data:')) {
                // Extract MIME type: data:<mime>;base64,...
                $mimeEnd = strpos($value, ';');
                $mime    = $mimeEnd !== false
                    ? substr($value, 5, $mimeEnd - 5)  // "text/javascript"
                    : 'unknown';

                // Grab first 10 chars of the payload for quick identification
                $payloadStart = strpos($value, ',');
                $preview      = $payloadStart !== false
                    ? substr($value, $payloadStart + 1, 10)
                    : '';

                $context[$key] = "data:{$mime};base64,{$preview}…[base64]";
            }
        }

        return $context;
    }

    // ── Internal file writer ──────────────────────────────────────────────────

    private static function writeToFile(string $entry): void
    {
        $logFile = LOG_FILE_PATH;
        $logDir  = dirname($logFile);

        // Create log directory if it doesn't exist
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                error_log("[AppLogger] Failed to create log directory: {$logDir}");
                return;
            }
        }

        // Append to log file
        if (file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX) === false) {
            error_log("[AppLogger] Failed to write to log file: {$logFile}");
        }
    }
}
