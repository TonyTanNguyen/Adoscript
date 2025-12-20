<?php
/**
 * File Upload API
 * Handles script and image uploads
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'script':
        requireAuthApi();
        uploadScript();
        break;
    case 'image':
        requireAuthApi();
        uploadImage();
        break;
    case 'images':
        requireAuthApi();
        uploadMultipleImages();
        break;
    case 'download':
        serveDownload();
        break;
    case 'delete':
        requireAuthApi();
        deleteFile();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Upload script file
 */
function uploadScript() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = isset($_FILES['file']) ? getUploadErrorMessage($_FILES['file']['error']) : 'No file uploaded';
        errorResponse($errorMessage);
    }

    $file = $_FILES['file'];

    // Validate file type
    if (!isValidScriptFile($file['name'])) {
        errorResponse('Invalid file type. Allowed: ' . implode(', ', ALLOWED_SCRIPT_TYPES));
    }

    // Validate file size
    if ($file['size'] > MAX_SCRIPT_SIZE) {
        errorResponse('File too large. Maximum size: ' . formatFileSize(MAX_SCRIPT_SIZE));
    }

    // Generate unique filename
    $filename = generateUniqueFilename($file['name']);
    $targetPath = SCRIPTS_PATH . $filename;

    // Ensure directory exists
    if (!is_dir(SCRIPTS_PATH)) {
        mkdir(SCRIPTS_PATH, 0755, true);
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        errorResponse('Failed to save file');
    }

    successResponse([
        'file_path' => $filename,
        'file_size' => formatFileSize($file['size']),
        'original_name' => $file['name']
    ], 'File uploaded successfully');
}

/**
 * Upload single image
 */
function uploadImage() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = isset($_FILES['file']) ? getUploadErrorMessage($_FILES['file']['error']) : 'No file uploaded';
        errorResponse($errorMessage);
    }

    $file = $_FILES['file'];

    // Validate file type
    if (!isValidImageFile($file['name'])) {
        errorResponse('Invalid file type. Allowed: ' . implode(', ', ALLOWED_IMAGE_TYPES));
    }

    // Validate file size
    if ($file['size'] > MAX_IMAGE_SIZE) {
        errorResponse('File too large. Maximum size: ' . formatFileSize(MAX_IMAGE_SIZE));
    }

    // Generate unique filename
    $filename = generateUniqueFilename($file['name']);
    $targetPath = IMAGES_PATH . $filename;

    // Ensure directory exists
    if (!is_dir(IMAGES_PATH)) {
        mkdir(IMAGES_PATH, 0755, true);
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        errorResponse('Failed to save file');
    }

    successResponse([
        'image_path' => $filename,
        'url' => 'uploads/images/' . $filename
    ], 'Image uploaded successfully');
}

/**
 * Upload multiple images
 */
function uploadMultipleImages() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    if (!isset($_FILES['files']) || !is_array($_FILES['files']['name'])) {
        errorResponse('No files uploaded');
    }

    $uploaded = [];
    $errors = [];

    // Ensure directory exists
    if (!is_dir(IMAGES_PATH)) {
        mkdir(IMAGES_PATH, 0755, true);
    }

    $fileCount = count($_FILES['files']['name']);

    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = $_FILES['files']['name'][$i] . ': ' . getUploadErrorMessage($_FILES['files']['error'][$i]);
            continue;
        }

        $name = $_FILES['files']['name'][$i];
        $tmpName = $_FILES['files']['tmp_name'][$i];
        $size = $_FILES['files']['size'][$i];

        // Validate file type
        if (!isValidImageFile($name)) {
            $errors[] = "$name: Invalid file type";
            continue;
        }

        // Validate file size
        if ($size > MAX_IMAGE_SIZE) {
            $errors[] = "$name: File too large";
            continue;
        }

        // Generate unique filename and save
        $filename = generateUniqueFilename($name);
        $targetPath = IMAGES_PATH . $filename;

        if (move_uploaded_file($tmpName, $targetPath)) {
            $uploaded[] = [
                'image_path' => $filename,
                'url' => 'uploads/images/' . $filename,
                'original_name' => $name
            ];
        } else {
            $errors[] = "$name: Failed to save";
        }
    }

    successResponse([
        'uploaded' => $uploaded,
        'errors' => $errors,
        'count' => count($uploaded)
    ], count($uploaded) . ' file(s) uploaded');
}

/**
 * Serve file download
 */
function serveDownload() {
    $file = $_GET['file'] ?? '';

    if (empty($file)) {
        errorResponse('File not specified');
    }

    // Sanitize filename to prevent directory traversal
    $file = basename($file);
    $filePath = SCRIPTS_PATH . $file;

    if (!file_exists($filePath)) {
        errorResponse('File not found', 404);
    }

    // Get file info
    $fileSize = filesize($filePath);
    $fileName = $file;

    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output file
    readfile($filePath);
    exit();
}

/**
 * Delete file
 */
function deleteFile() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? $_POST['type'] ?? '';
    $path = $input['path'] ?? $_POST['path'] ?? '';

    if (empty($type) || empty($path)) {
        errorResponse('Type and path are required');
    }

    // Sanitize path
    $path = basename($path);

    if ($type === 'script') {
        $fullPath = SCRIPTS_PATH . $path;
    } elseif ($type === 'image') {
        $fullPath = IMAGES_PATH . $path;
    } else {
        errorResponse('Invalid file type');
    }

    if (!file_exists($fullPath)) {
        errorResponse('File not found', 404);
    }

    if (unlink($fullPath)) {
        successResponse([], 'File deleted successfully');
    } else {
        errorResponse('Failed to delete file');
    }
}

/**
 * Get upload error message
 */
function getUploadErrorMessage($errorCode) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server maximum size',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
    ];

    return $errors[$errorCode] ?? 'Unknown upload error';
}
