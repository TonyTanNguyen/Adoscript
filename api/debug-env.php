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

// Get ALL lines with key=value (redacted values)
$lines = file_exists($envPath) ? file($envPath, FILE_IGNORE_NEW_LINES) : [];
$keyValueLines = [];
foreach ($lines as $i => $line) {
    $line = trim($line);
    if (!empty($line) && strpos($line, '#') !== 0 && strpos($line, '=') !== false) {
        list($key, $val) = explode('=', $line, 2);
        $keyValueLines[] = 'Line ' . ($i+1) . ': ' . trim($key) . '=' . (strlen($val) > 0 ? '[' . strlen($val) . ' chars]' : '[EMPTY]');
    }
}

// Manual parse test
$manualParseResult = [];
foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line) || strpos($line, '#') === 0) continue;
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $manualParseResult[$key] = strlen($value) . ' chars';
    }
}

$debug = [
    'env_path' => $envPath,
    'env_exists' => file_exists($envPath) ? 'YES' : 'NO',
    'env_size' => file_exists($envPath) ? filesize($envPath) . ' bytes' : 'N/A',
    'total_lines' => count($lines),
    'key_value_lines' => $keyValueLines,
    'manual_parse_keys' => array_keys($manualParseResult),
    'manual_parse_result' => $manualParseResult,
    'env_loader_exists' => function_exists('loadEnv') ? 'YES' : 'NO',
    'env_function_exists' => function_exists('env') ? 'YES' : 'NO',
    'claude_loaded_via_define' => defined('CLAUDE_API_KEY') ? (empty(CLAUDE_API_KEY) ? 'DEFINED BUT EMPTY' : 'YES') : 'NOT DEFINED',
    'getenv_claude' => getenv('CLAUDE_API_KEY') ? 'YES' : 'NO',
    'env_array_claude' => isset($_ENV['CLAUDE_API_KEY']) ? 'YES' : 'NO',
];

successResponse(['debug' => $debug]);
