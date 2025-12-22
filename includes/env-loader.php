<?php
/**
 * Environment Variable Loader
 *
 * Loads environment variables from .env file into $_ENV and getenv()
 * This provides a more secure way to manage sensitive credentials
 */

function loadEnv($path = null) {
    if ($path === null) {
        $path = dirname(__DIR__) . '/.env';
    }

    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }

        // Parse key=value
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            // Set environment variable
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }

    return true;
}

/**
 * Get environment variable with optional default
 */
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? $default;
    }
    return $value;
}

// Auto-load .env file
loadEnv();
