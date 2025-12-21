<?php
/**
 * Download Page
 * Handles script downloads with token validation
 * URL: /download.php?token=xxx
 */

// Start output buffering to prevent any premature output
ob_start();

// Load config without the JSON headers
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Database and path configuration only
define('DB_PATH', __DIR__ . '/data/adoscript.db');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('SCRIPTS_PATH', UPLOAD_PATH . 'scripts/');

require_once __DIR__ . '/includes/db.php';

// Clear buffer and remove any headers
ob_end_clean();

$token = $_GET['token'] ?? '';

if (empty($token)) {
    error_log("Download: No token provided");
    showError('Invalid Download Link', 'No download token provided.');
}

error_log("Download: Processing token " . substr($token, 0, 16) . "...");

try {
    $db = getDB();

    // Get order by token
    $stmt = $db->prepare("
        SELECT o.*, s.file_path, s.name as script_name, s.slug, s.application
        FROM orders o
        JOIN scripts s ON o.script_id = s.id
        WHERE o.download_token = ?
        AND o.status = 'completed'
    ");
    $stmt->execute([$token]);
    $order = $stmt->fetch();

    if (!$order) {
        error_log("Download: Order not found for token");
        showError('Download Not Found', 'This download link is invalid or has already been used.');
    }
    
    error_log("Download: Found order ID " . $order['id'] . ", file: " . $order['file_path']);

    // Check if token expired
    if ($order['token_expires_at'] && strtotime($order['token_expires_at']) < time()) {
        showError('Download Expired', 'This download link has expired. Please contact support if you need assistance.');
    }

    // Check if file exists
    $filePath = SCRIPTS_PATH . $order['file_path'];
    if (empty($order['file_path']) || !file_exists($filePath)) {
        showError('File Not Available', 'The script file is currently unavailable. Please contact support.');
    }

    // Update download count
    $stmt = $db->prepare("UPDATE orders SET download_count = download_count + 1 WHERE id = ?");
    $stmt->execute([$order['id']]);

    // Also update script download count
    $stmt = $db->prepare("UPDATE scripts SET downloads = downloads + 1 WHERE id = ?");
    $stmt->execute([$order['script_id']]);

    // Send file for download
    $filename = basename($order['file_path']);
    $filesize = filesize($filePath);

    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $filesize);
    header('Content-Transfer-Encoding: binary');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($filePath);
    exit;

} catch (PDOException $e) {
    error_log('Download error: ' . $e->getMessage());
    showError('Error', 'An error occurred while processing your download. Please try again.');
}

/**
 * Show error page
 */
function showError($title, $message) {
    header('Content-Type: text/html; charset=UTF-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Adoscript</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-xl shadow-lg p-8 text-center">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-exclamation-triangle text-3xl text-red-500"></i>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($title) ?></h1>
        <p class="text-gray-600 mb-6"><?= htmlspecialchars($message) ?></p>
        <div class="space-y-3">
            <a href="scripts.html" class="block w-full py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                <i class="fas fa-arrow-left mr-2"></i> Browse Scripts
            </a>
            <a href="mailto:support@adoscript.com" class="block w-full py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-envelope mr-2"></i> Contact Support
            </a>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}
