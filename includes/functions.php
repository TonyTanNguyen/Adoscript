<?php
/**
 * Helper Functions
 */

/**
 * Generate a URL-friendly slug from a string
 */
function generateSlug($string) {
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Generate a unique slug by checking database
 */
function generateUniqueSlug($name, $db, $excludeId = null) {
    $baseSlug = generateSlug($name);
    $slug = $baseSlug;
    $counter = 1;

    while (true) {
        $sql = "SELECT id FROM scripts WHERE slug = ?";
        $params = [$slug];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if (!$stmt->fetch()) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
}

/**
 * Format file size for display
 */
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

// Alias for formatFileSize
function formatBytes($bytes) {
    return formatFileSize($bytes);
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Validate file type
 */
function isValidScriptFile($filename) {
    $ext = getFileExtension($filename);
    return in_array($ext, ALLOWED_SCRIPT_TYPES);
}

/**
 * Validate image type
 */
function isValidImageFile($filename) {
    $ext = getFileExtension($filename);
    return in_array($ext, ALLOWED_IMAGE_TYPES);
}

/**
 * Generate unique filename
 */
function generateUniqueFilename($originalName) {
    $ext = getFileExtension($originalName);
    $name = pathinfo($originalName, PATHINFO_FILENAME);
    $slug = generateSlug($name);
    $unique = uniqid();
    return "{$slug}-{$unique}.{$ext}";
}

/**
 * Sanitize HTML content
 */
function sanitizeHtml($html) {
    // Allow basic formatting tags
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><h4><h5><h6><a><code><pre>';
    return strip_tags($html, $allowed);
}

/**
 * Clean input string
 */
function cleanInput($input) {
    if (is_array($input)) {
        return array_map('cleanInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Get application color
 */
function getAppColor($app) {
    $colors = [
        'indesign' => '#FF3366',
        'photoshop' => '#31A8FF',
        'illustrator' => '#FF9A00'
    ];
    return $colors[$app] ?? '#7f22ea';
}

/**
 * Get application icon class
 */
function getAppIcon($app) {
    $icons = [
        'indesign' => 'fa-file-alt',
        'photoshop' => 'fa-image',
        'illustrator' => 'fa-pen-nib'
    ];
    return $icons[$app] ?? 'fa-code';
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
