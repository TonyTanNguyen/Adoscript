<?php
/**
 * PayPal Payment API
 * Handles PayPal order creation and payment capture
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

// PayPal API URLs
function getPayPalBaseUrl() {
    $mode = defined('PAYPAL_MODE') ? PAYPAL_MODE : 'sandbox';
    return $mode === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com';
}

// Get PayPal access token
function getPayPalAccessToken() {
    $clientId = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
    $clientSecret = defined('PAYPAL_CLIENT_SECRET') ? PAYPAL_CLIENT_SECRET : '';

    if (empty($clientId) || empty($clientSecret)) {
        return false;
    }

    $ch = curl_init(getPayPalBaseUrl() . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_USERPWD => $clientId . ':' . $clientSecret,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en_US',
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 200) {
        error_log("PayPal auth failed: $response");
        return false;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? false;
}

// Handle actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'create-order':
        createPayPalOrder();
        break;
    case 'capture-order':
        capturePayPalOrder();
        break;
    case 'get-client-id':
        getClientId();
        break;
    default:
        errorResponse('Invalid action', 400);
}

/**
 * Get PayPal Client ID for frontend
 */
function getClientId() {
    $clientId = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
    $mode = defined('PAYPAL_MODE') ? PAYPAL_MODE : 'sandbox';

    if (empty($clientId)) {
        errorResponse('PayPal is not configured');
    }

    successResponse([
        'client_id' => $clientId,
        'mode' => $mode
    ]);
}

/**
 * Create PayPal order
 */
function createPayPalOrder() {
    $scriptId = intval($_POST['script_id'] ?? 0);
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

    if (!$scriptId) {
        errorResponse('Script ID is required');
    }

    if (!$email) {
        errorResponse('Valid email is required');
    }

    try {
        $db = getDB();

        // Get script details
        $stmt = $db->prepare("SELECT * FROM scripts WHERE id = ? AND status = 'published'");
        $stmt->execute([$scriptId]);
        $script = $stmt->fetch();

        if (!$script) {
            errorResponse('Script not found', 404);
        }

        if ($script['price_type'] === 'free' || $script['price'] <= 0) {
            errorResponse('This script is free');
        }

        $price = number_format((float)$script['price'], 2, '.', '');

        // Get access token
        $accessToken = getPayPalAccessToken();
        if (!$accessToken) {
            errorResponse('PayPal configuration error');
        }

        // Create PayPal order
        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'script_' . $scriptId,
                    'description' => $script['name'],
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => $price
                    ]
                ]
            ],
            'application_context' => [
                'brand_name' => 'Adoscript',
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
                'return_url' => SITE_URL . '/order-success.html',
                'cancel_url' => SITE_URL . '/script-detail.html?slug=' . $script['slug']
            ]
        ];

        $ch = curl_init(getPayPalBaseUrl() . '/v2/checkout/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($orderData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
                'PayPal-Request-Id: ' . uniqid('order_', true)
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $paypalOrder = json_decode($response, true);

        if ($httpCode !== 201 || empty($paypalOrder['id'])) {
            error_log("PayPal create order failed: $response");
            errorResponse('Failed to create PayPal order');
        }

        // Store pending order in database
        $orderId = 'ORD-' . strtoupper(bin2hex(random_bytes(8)));
        $stmt = $db->prepare("
            INSERT INTO orders (order_id, script_id, customer_email, amount, currency, payment_method, payment_id, status, created_at)
            VALUES (?, ?, ?, ?, 'USD', 'paypal', ?, 'pending', CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$orderId, $scriptId, $email, $price, $paypalOrder['id']]);

        successResponse([
            'order_id' => $paypalOrder['id']
        ]);

    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        errorResponse('Database error', 500);
    }
}

/**
 * Capture PayPal payment after approval
 */
function capturePayPalOrder() {
    $orderId = $_POST['order_id'] ?? '';

    if (empty($orderId)) {
        errorResponse('Order ID is required');
    }

    try {
        $db = getDB();

        // Get pending order
        $stmt = $db->prepare("SELECT * FROM orders WHERE payment_id = ? AND status = 'pending'");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            errorResponse('Order not found', 404);
        }

        // Get access token
        $accessToken = getPayPalAccessToken();
        if (!$accessToken) {
            errorResponse('PayPal configuration error');
        }

        // Capture the payment
        $ch = curl_init(getPayPalBaseUrl() . '/v2/checkout/orders/' . $orderId . '/capture');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $captureResult = json_decode($response, true);

        if ($httpCode !== 201 || ($captureResult['status'] ?? '') !== 'COMPLETED') {
            error_log("PayPal capture failed: $response");

            // Update order status to failed
            $stmt = $db->prepare("UPDATE orders SET status = 'failed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$order['id']]);

            errorResponse('Payment capture failed');
        }

        // Get capture details
        $captureId = $captureResult['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';
        
        // Use the original email from checkout form, NOT the PayPal account email
        $customerEmail = $order['customer_email'];

        // Generate download token
        $downloadToken = bin2hex(random_bytes(32));
        $tokenExpiry = date('Y-m-d H:i:s', strtotime('+7 days'));

        // Update order as completed (keep original customer_email from checkout form)
        $stmt = $db->prepare("
            UPDATE orders
            SET status = 'completed',
                transaction_id = ?,
                download_token = ?,
                token_expires_at = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$captureId, $downloadToken, $tokenExpiry, $order['id']]);

        // Get script details for response
        $stmt = $db->prepare("SELECT name, slug FROM scripts WHERE id = ?");
        $stmt->execute([$order['script_id']]);
        $script = $stmt->fetch();

        // Build download URL (using root download.php for better compatibility)
        $downloadUrl = SITE_URL . '/download.php?token=' . $downloadToken;

        // Send order confirmation email to the EMAIL FROM CHECKOUT FORM (not PayPal email)
        $orderData = [
            'order_id' => $order['order_id'],
            'customer_email' => $customerEmail,
            'amount' => $order['amount'],
            'token_expires_at' => $tokenExpiry
        ];
        $emailSent = sendOrderConfirmationEmail($orderData, $script, $downloadUrl);

        if (!$emailSent) {
            error_log("Failed to send order confirmation email to: $customerEmail");
        }

        successResponse([
            'status' => 'completed',
            'download_token' => $downloadToken,
            'script_name' => $script['name'],
            'script_slug' => $script['slug'],
            'email' => $customerEmail,
            'email_sent' => $emailSent
        ], 'Payment successful!');

    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        errorResponse('Database error', 500);
    }
}
