<?php
/**
 * Debug script to check environment configuration
 * DELETE THIS FILE after debugging!
 */

require_once __DIR__ . '/config.php';

// Only allow if logged in as admin
require_once __DIR__ . '/../includes/auth-check.php';
requireAuthApi();

$envPath = dirname(__DIR__) . '/.env';
$envContent = file_exists($envPath) ? file_get_contents($envPath) : 'FILE NOT FOUND';

// Check first few bytes for BOM or issues
$firstBytes = file_exists($envPath) ? bin2hex(substr(file_get_contents($envPath), 0, 10)) : '';

// Get first 3 lines (redacted)
$lines = file_exists($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];
$sampleLines = [];
foreach (array_slice($lines, 0, 5) as $line) {
    // Show structure but hide values
    if (strpos($line, '=') !== false) {
        list($key, $val) = explode('=', $line, 2);
        $sampleLines[] = trim($key) . '=' . (strlen($val) > 0 ? '[' . strlen($val) . ' chars]' : '[EMPTY]');
    } else {
        $sampleLines[] = substr($line, 0, 30) . (strlen($line) > 30 ? '...' : '');
    }
}

$debug = [
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'not set',
    'env_path' => $envPath,
    'env_exists' => file_exists($envPath) ? 'YES' : 'NO',
    'env_readable' => is_readable($envPath) ? 'YES' : 'NO',
    'env_size' => file_exists($envPath) ? filesize($envPath) . ' bytes' : 'N/A',
    'first_bytes_hex' => $firstBytes,
    'has_bom' => (substr($firstBytes, 0, 6) === 'efbbbf') ? 'YES - BOM DETECTED!' : 'NO',
    'sample_lines' => $sampleLines,
    'line_ending' => file_exists($envPath) ? (strpos($envContent, "\r\n") !== false ? 'CRLF (Windows)' : 'LF (Unix)') : 'N/A',
    'claude_api_key_loaded' => defined('CLAUDE_API_KEY') && !empty(CLAUDE_API_KEY) ? 'YES (length: ' . strlen(CLAUDE_API_KEY) . ')' : 'NO',
    'getenv_test' => getenv('CLAUDE_API_KEY') ? 'YES' : 'NO',
    'env_array_test' => isset($_ENV['CLAUDE_API_KEY']) ? 'YES' : 'NO',
];

successResponse(['debug' => $debug]);
