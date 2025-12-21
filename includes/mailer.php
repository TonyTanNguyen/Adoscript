<?php
/**
 * Email Helper Functions
 * Simple SMTP mailer for sending order confirmation emails
 */

/**
 * Send email via SMTP
 */
function sendEmail($to, $subject, $htmlBody, $textBody = '') {
    // Check if SMTP is configured
    if (!defined('SMTP_HOST') || !defined('SMTP_USERNAME') || !defined('SMTP_PASSWORD')) {
        error_log('Email not sent: SMTP not configured - missing SMTP_HOST, SMTP_USERNAME, or SMTP_PASSWORD');
        return false;
    }
    
    // Check for placeholder values
    if (strpos(SMTP_PASSWORD, 'your-') !== false || SMTP_PASSWORD === '' || 
        strpos(SMTP_USERNAME, 'your-') !== false) {
        error_log('Email not sent: SMTP credentials contain placeholder values');
        return false;
    }

    $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : SMTP_USERNAME;
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Adoscript';

    // Create boundary for multipart email
    $boundary = md5(time());

    // Headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'X-Mailer: PHP/' . phpversion()
    ];

    // Plain text fallback
    if (empty($textBody)) {
        $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
    }

    // Build email body
    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $textBody . "\r\n\r\n";

    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";

    $body .= "--{$boundary}--";

    // Try SMTP first, fallback to mail()
    $sent = sendViaSMTP($to, $subject, $body, $headers);

    if (!$sent) {
        // Fallback to PHP mail()
        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    if (!$sent) {
        error_log("Failed to send email to: $to");
    }

    return $sent;
}

/**
 * Send email via SMTP socket connection
 * Supports both SSL (port 465) and TLS/STARTTLS (port 587)
 */
function sendViaSMTP($to, $subject, $body, $headers) {
    $host = SMTP_HOST;
    $port = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $username = SMTP_USERNAME;
    $password = SMTP_PASSWORD;
    $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : SMTP_USERNAME;

    try {
        $socket = false;
        $useSSL = ($port == 465);
        
        // Create stream context with SSL options
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        if ($useSSL) {
            // Port 465: Direct SSL connection (Hostinger recommended)
            $socket = @stream_socket_client(
                'ssl://' . $host . ':' . $port,
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
        } else {
            // Port 587: Plain connection then upgrade with STARTTLS
            $socket = @stream_socket_client(
                'tcp://' . $host . ':' . $port,
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
        }

        if (!$socket) {
            error_log("SMTP connection failed to $host:$port - $errstr ($errno)");
            return false;
        }

        // Read greeting
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            error_log("SMTP greeting failed: $response");
            fclose($socket);
            return false;
        }

        // Get hostname for EHLO
        $hostname = gethostname() ?: 'localhost';

        // EHLO
        fputs($socket, "EHLO {$hostname}\r\n");
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }

        // STARTTLS for port 587 (TLS)
        if (!$useSSL && strpos($response, 'STARTTLS') !== false) {
            fputs($socket, "STARTTLS\r\n");
            $starttlsResponse = fgets($socket, 515);
            
            if (substr($starttlsResponse, 0, 3) != '220') {
                error_log("STARTTLS failed: $starttlsResponse");
                fclose($socket);
                return false;
            }
            
            // Enable TLS encryption
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
            if (!$crypto) {
                error_log("Failed to enable TLS encryption");
                fclose($socket);
                return false;
            }

            // EHLO again after TLS
            fputs($socket, "EHLO {$hostname}\r\n");
            while ($line = fgets($socket, 515)) {
                if (substr($line, 3, 1) == ' ') break;
            }
        }

        // AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $authResponse = fgets($socket, 515);
        if (substr($authResponse, 0, 3) != '334') {
            error_log("AUTH LOGIN not accepted: $authResponse");
            fclose($socket);
            return false;
        }

        // Send username
        fputs($socket, base64_encode($username) . "\r\n");
        $userResponse = fgets($socket, 515);
        if (substr($userResponse, 0, 3) != '334') {
            error_log("SMTP username rejected: $userResponse");
            fclose($socket);
            return false;
        }

        // Send password
        fputs($socket, base64_encode($password) . "\r\n");
        $passResponse = fgets($socket, 515);
        if (substr($passResponse, 0, 3) != '235') {
            error_log("SMTP authentication failed: $passResponse");
            fclose($socket);
            return false;
        }

        // MAIL FROM
        fputs($socket, "MAIL FROM:<{$fromEmail}>\r\n");
        $fromResponse = fgets($socket, 515);
        if (substr($fromResponse, 0, 3) != '250') {
            error_log("MAIL FROM rejected: $fromResponse");
            fclose($socket);
            return false;
        }

        // RCPT TO
        fputs($socket, "RCPT TO:<{$to}>\r\n");
        $rcptResponse = fgets($socket, 515);
        if (substr($rcptResponse, 0, 3) != '250') {
            error_log("RCPT TO rejected: $rcptResponse");
            fclose($socket);
            return false;
        }

        // DATA
        fputs($socket, "DATA\r\n");
        $dataResponse = fgets($socket, 515);
        if (substr($dataResponse, 0, 3) != '354') {
            error_log("DATA command rejected: $dataResponse");
            fclose($socket);
            return false;
        }

        // Send headers and body
        fputs($socket, "To: {$to}\r\n");
        fputs($socket, "Subject: {$subject}\r\n");
        fputs($socket, implode("\r\n", $headers) . "\r\n");
        fputs($socket, "\r\n");
        fputs($socket, $body . "\r\n");
        fputs($socket, ".\r\n");

        $response = fgets($socket, 515);
        $success = substr($response, 0, 3) == '250';

        if (!$success) {
            error_log("Email send failed: $response");
        }

        // QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return $success;

    } catch (Exception $e) {
        error_log("SMTP error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send order confirmation email with download link
 */
function sendOrderConfirmationEmail($order, $script, $downloadUrl) {
    $to = $order['customer_email'];
    $subject = "Your Adoscript Purchase: " . $script['name'];

    $htmlBody = getOrderEmailTemplate($order, $script, $downloadUrl);

    return sendEmail($to, $subject, $htmlBody);
}

/**
 * Get HTML email template for order confirmation
 */
function getOrderEmailTemplate($order, $script, $downloadUrl) {
    $scriptName = htmlspecialchars($script['name']);
    $amount = number_format($order['amount'], 2);
    $orderId = htmlspecialchars($order['order_id']);
    $expiryDate = date('F j, Y', strtotime($order['token_expires_at']));

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <tr>
            <td>
                <!-- Header -->
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #7f22ea; border-radius: 12px 12px 0 0; padding: 30px; text-align: center;">
                    <tr>
                        <td>
                            <h1 style="color: white; margin: 0; font-size: 24px;">Thank You for Your Purchase!</h1>
                        </td>
                    </tr>
                </table>

                <!-- Content -->
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: white; padding: 30px;">
                    <tr>
                        <td>
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Your order has been confirmed. You can download your script using the button below.
                            </p>

                            <!-- Order Details -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                                <tr>
                                    <td>
                                        <h3 style="color: #111827; margin: 0 0 15px 0; font-size: 16px;">Order Details</h3>
                                        <table width="100%" cellpadding="5" cellspacing="0">
                                            <tr>
                                                <td style="color: #6b7280; font-size: 14px;">Order ID:</td>
                                                <td style="color: #111827; font-size: 14px; text-align: right;">{$orderId}</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #6b7280; font-size: 14px;">Script:</td>
                                                <td style="color: #111827; font-size: 14px; text-align: right;">{$scriptName}</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #6b7280; font-size: 14px;">Amount:</td>
                                                <td style="color: #111827; font-size: 14px; text-align: right; font-weight: bold;">\${$amount}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Download Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{$downloadUrl}" style="display: inline-block; background-color: #7f22ea; color: white; text-decoration: none; padding: 15px 40px; border-radius: 8px; font-size: 16px; font-weight: bold;">
                                            Download Your Script
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #6b7280; font-size: 14px; line-height: 1.6; margin: 0; text-align: center;">
                                This download link expires on <strong>{$expiryDate}</strong>
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Footer -->
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 0 0 12px 12px; padding: 20px; text-align: center;">
                    <tr>
                        <td>
                            <p style="color: #6b7280; font-size: 12px; margin: 0 0 10px 0;">
                                If the button doesn't work, copy and paste this link into your browser:
                            </p>
                            <p style="color: #7f22ea; font-size: 12px; margin: 0 0 20px 0; word-break: break-all;">
                                {$downloadUrl}
                            </p>
                            <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                                &copy; 2025 Adoscript. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
