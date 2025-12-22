<?php
/**
 * Debug script to check environment configuration
 * DELETE THIS FILE after debugging!
 */

require_once __DIR__ . '/config.php';

// Only allow if logged in as admin
require_once __DIR__ . '/../includes/auth-check.php';
requireAuthApi();

$debug = [
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'not set',
    'script_dir' => __DIR__,
    'parent_dir' => dirname(__DIR__),
    'env_paths_checked' => [
        dirname(__DIR__) . '/.env' => file_exists(dirname(__DIR__) . '/.env') ? 'EXISTS' : 'NOT FOUND',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/.env' => file_exists(($_SERVER['DOCUMENT_ROOT'] ?? '') . '/.env') ? 'EXISTS' : 'NOT FOUND',
    ],
    'claude_api_key_loaded' => defined('CLAUDE_API_KEY') && !empty(CLAUDE_API_KEY) ? 'YES (length: ' . strlen(CLAUDE_API_KEY) . ')' : 'NO',
    'paypal_client_id_loaded' => defined('PAYPAL_CLIENT_ID') && !empty(PAYPAL_CLIENT_ID) ? 'YES' : 'NO',
    'smtp_host_loaded' => defined('SMTP_HOST') && !empty(SMTP_HOST) ? SMTP_HOST : 'NO',
];

successResponse(['debug' => $debug]);
