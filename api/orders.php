<?php
/**
 * Orders API
 * Handles order listing and management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth-check.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    // Public endpoints
    case 'verify-download':
        verifyDownload();
        break;
    case 'download':
        downloadPaidScript();
        break;

    // Admin endpoints (require auth)
    case 'list':
        requireAuthApi();
        getOrders();
        break;
    case 'get':
        requireAuthApi();
        getOrder();
        break;
    case 'stats':
        requireAuthApi();
        getOrderStats();
        break;
    case 'export':
        requireAuthApi();
        exportOrders();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Verify download token and get order details (public)
 */
function verifyDownload() {
    $token = $_GET['token'] ?? '';

    if (empty($token)) {
        errorResponse('Download token is required');
    }

    try {
        $db = getDB();

        // Get order by token
        $stmt = $db->prepare("
            SELECT o.*, s.name, s.slug, s.application, s.file_path, s.file_size
            FROM orders o
            JOIN scripts s ON o.script_id = s.id
            WHERE o.download_token = ?
            AND o.status = 'completed'
        ");
        $stmt->execute([$token]);
        $result = $stmt->fetch();

        if (!$result) {
            errorResponse('Invalid or expired download link', 404);
        }

        // Check if token expired
        if ($result['token_expires_at'] && strtotime($result['token_expires_at']) < time()) {
            errorResponse('Download link has expired', 410);
        }

        successResponse([
            'order' => [
                'id' => $result['id'],
                'payment_id' => $result['payment_id'],
                'customer_email' => $result['customer_email'],
                'amount' => $result['amount'],
                'status' => $result['status'],
                'token_expires_at' => $result['token_expires_at'],
                'download_count' => $result['download_count']
            ],
            'script' => [
                'name' => $result['name'],
                'slug' => $result['slug'],
                'application' => $result['application'],
                'file_size' => $result['file_size']
            ]
        ]);

    } catch (PDOException $e) {
        errorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

/**
 * Download paid script file (public)
 */
function downloadPaidScript() {
    $token = $_GET['token'] ?? '';

    if (empty($token)) {
        errorResponse('Download token is required');
    }

    try {
        $db = getDB();

        // Get order by token
        $stmt = $db->prepare("
            SELECT o.*, s.file_path, s.name
            FROM orders o
            JOIN scripts s ON o.script_id = s.id
            WHERE o.download_token = ?
            AND o.status = 'completed'
        ");
        $stmt->execute([$token]);
        $order = $stmt->fetch();

        if (!$order) {
            header('Content-Type: text/html');
            echo '<h1>Download Not Found</h1><p>Invalid or expired download link.</p>';
            echo '<a href="scripts.html">Browse Scripts</a>';
            exit;
        }

        // Check if token expired
        if ($order['token_expires_at'] && strtotime($order['token_expires_at']) < time()) {
            header('Content-Type: text/html');
            echo '<h1>Download Expired</h1><p>This download link has expired.</p>';
            echo '<a href="scripts.html">Browse Scripts</a>';
            exit;
        }

        // Check if file exists
        $filePath = SCRIPTS_PATH . $order['file_path'];
        if (empty($order['file_path']) || !file_exists($filePath)) {
            header('Content-Type: text/html');
            echo '<h1>File Not Found</h1><p>The script file is not available.</p>';
            echo '<a href="scripts.html">Browse Scripts</a>';
            exit;
        }

        // Update download count
        $stmt = $db->prepare("UPDATE orders SET download_count = download_count + 1 WHERE id = ?");
        $stmt->execute([$order['id']]);

        // Also update script download count
        $stmt = $db->prepare("UPDATE scripts SET downloads = downloads + 1 WHERE id = ?");
        $stmt->execute([$order['script_id']]);

        // Send file
        $filename = basename($order['file_path']);
        $filesize = filesize($filePath);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($filePath);
        exit;

    } catch (PDOException $e) {
        error_log('Download error: ' . $e->getMessage());
        header('Content-Type: text/html');
        echo '<h1>Error</h1><p>An error occurred. Please try again.</p>';
        exit;
    }
}

/**
 * Get all orders with pagination and filters
 */
function getOrders() {
    try {
        $db = getDB();

        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if (!empty($_GET['status'])) {
            $where[] = "o.status = ?";
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['payment_method'])) {
            $where[] = "o.payment_method = ?";
            $params[] = $_GET['payment_method'];
        }
        if (!empty($_GET['search'])) {
            $where[] = "(o.order_id LIKE ? OR o.customer_email LIKE ? OR s.name LIKE ?)";
            $searchTerm = '%' . $_GET['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        if (!empty($_GET['date_from'])) {
            $where[] = "DATE(o.created_at) >= ?";
            $params[] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $where[] = "DATE(o.created_at) <= ?";
            $params[] = $_GET['date_to'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Get total count
        $countSql = "SELECT COUNT(*) FROM orders o LEFT JOIN scripts s ON o.script_id = s.id $whereClause";
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetchColumn();

        // Get orders
        $sql = "SELECT o.*, s.name as script_name, s.slug as script_slug
                FROM orders o
                LEFT JOIN scripts s ON o.script_id = s.id
                $whereClause
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        successResponse([
            'orders' => $orders,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]);

    } catch (PDOException $e) {
        errorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

/**
 * Get single order details
 */
function getOrder() {
    $id = intval($_GET['id'] ?? 0);
    $orderId = $_GET['order_id'] ?? '';

    if (!$id && empty($orderId)) {
        errorResponse('Order ID is required');
    }

    try {
        $db = getDB();

        if ($id) {
            $stmt = $db->prepare("SELECT o.*, s.name as script_name, s.slug as script_slug
                                 FROM orders o
                                 LEFT JOIN scripts s ON o.script_id = s.id
                                 WHERE o.id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $db->prepare("SELECT o.*, s.name as script_name, s.slug as script_slug
                                 FROM orders o
                                 LEFT JOIN scripts s ON o.script_id = s.id
                                 WHERE o.order_id = ?");
            $stmt->execute([$orderId]);
        }

        $order = $stmt->fetch();

        if (!$order) {
            errorResponse('Order not found', 404);
        }

        successResponse(['order' => $order]);

    } catch (PDOException $e) {
        errorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

/**
 * Get order statistics
 */
function getOrderStats() {
    try {
        $db = getDB();

        // Total orders
        $stmt = $db->query("SELECT COUNT(*) FROM orders");
        $total_orders = $stmt->fetchColumn();

        // Total revenue
        $stmt = $db->query("SELECT COALESCE(SUM(amount), 0) FROM orders WHERE status = 'completed'");
        $total_revenue = $stmt->fetchColumn();

        // This month revenue
        $stmt = $db->query("SELECT COALESCE(SUM(amount), 0) FROM orders
                          WHERE status = 'completed'
                          AND strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now')");
        $month_revenue = $stmt->fetchColumn();

        // Average order value
        $stmt = $db->query("SELECT COALESCE(AVG(amount), 0) FROM orders WHERE status = 'completed'");
        $avg_order = $stmt->fetchColumn();

        // Orders by status
        $stmt = $db->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
        $by_status = $stmt->fetchAll();

        successResponse([
            'total_orders' => (int)$total_orders,
            'total_revenue' => (float)$total_revenue,
            'month_revenue' => (float)$month_revenue,
            'avg_order' => (float)$avg_order,
            'by_status' => $by_status
        ]);

    } catch (PDOException $e) {
        errorResponse('Database error: ' . $e->getMessage(), 500);
    }
}

/**
 * Export orders as CSV
 */
function exportOrders() {
    try {
        $db = getDB();

        $where = [];
        $params = [];

        if (!empty($_GET['status'])) {
            $where[] = "o.status = ?";
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['date_from'])) {
            $where[] = "DATE(o.created_at) >= ?";
            $params[] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $where[] = "DATE(o.created_at) <= ?";
            $params[] = $_GET['date_to'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT o.order_id, s.name as script_name, o.customer_email, o.amount,
                       o.payment_method, o.status, o.created_at
                FROM orders o
                LEFT JOIN scripts s ON o.script_id = s.id
                $whereClause
                ORDER BY o.created_at DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();

        // Generate CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="orders_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Order ID', 'Script', 'Customer Email', 'Amount', 'Payment Method', 'Status', 'Date']);

        foreach ($orders as $order) {
            fputcsv($output, [
                $order['order_id'],
                $order['script_name'],
                $order['customer_email'],
                '$' . number_format($order['amount'], 2),
                $order['payment_method'],
                $order['status'],
                $order['created_at']
            ]);
        }

        fclose($output);
        exit;

    } catch (PDOException $e) {
        errorResponse('Database error: ' . $e->getMessage(), 500);
    }
}
