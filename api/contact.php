<?php
/**
 * Contact Form API
 * Sends contact form submissions to support@adoscript.com
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/mailer.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'send';

if ($action === 'send') {
    sendContactMessage();
} else {
    errorResponse('Invalid action', 400);
}

/**
 * Send contact form message
 */
function sendContactMessage() {
    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Validate required fields
    if (empty($name)) {
        errorResponse('Name is required');
    }
    if (!$email) {
        errorResponse('Valid email address is required');
    }
    if (empty($message)) {
        errorResponse('Message is required');
    }

    // Map subject values to readable text
    $subjectMap = [
        'support' => 'Technical Support',
        'sales' => 'Sales Inquiry',
        'custom' => 'Custom Script Request',
        'feedback' => 'Feedback',
        'other' => 'Other'
    ];
    $subjectText = $subjectMap[$subject] ?? ($subject ?: 'General Inquiry');

    // Build email content
    $emailSubject = "[Adoscript Contact] {$subjectText} from {$name}";
    
    $htmlBody = buildContactEmailHtml($name, $email, $subjectText, $message);
    $textBody = buildContactEmailText($name, $email, $subjectText, $message);

    // Send email to support
    $sent = sendEmailToSupport($emailSubject, $htmlBody, $textBody, $email, $name);

    if ($sent) {
        successResponse([], 'Your message has been sent successfully. We\'ll get back to you soon!');
    } else {
        errorResponse('Failed to send message. Please try again or email us directly at support@adoscript.com', 500);
    }
}

/**
 * Send email to support address
 */
function sendEmailToSupport($subject, $htmlBody, $textBody, $replyTo, $replyToName) {
    $to = 'support@adoscript.com';
    
    // Check if SMTP is configured
    if (!defined('SMTP_HOST') || !defined('SMTP_USERNAME') || !defined('SMTP_PASSWORD')) {
        error_log('Contact email not sent: SMTP not configured');
        return false;
    }
    
    if (strpos(SMTP_PASSWORD, 'your-') !== false || SMTP_PASSWORD === '') {
        error_log('Contact email not sent: SMTP credentials contain placeholder values');
        return false;
    }

    $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : SMTP_USERNAME;
    $fromName = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'Adoscript';

    // Create boundary for multipart email
    $boundary = md5(time());

    // Headers with Reply-To set to the sender's email
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $replyToName . ' <' . $replyTo . '>',
        'X-Mailer: PHP/' . phpversion()
    ];

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

    // Send via SMTP
    return sendViaSMTP($to, $subject, $body, $headers);
}

/**
 * Build HTML email content
 */
function buildContactEmailHtml($name, $email, $subject, $message) {
    $name = htmlspecialchars($name);
    $email = htmlspecialchars($email);
    $subject = htmlspecialchars($subject);
    $message = nl2br(htmlspecialchars($message));
    $date = date('F j, Y \a\t g:i A');

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
                            <h1 style="color: white; margin: 0; font-size: 24px;">New Contact Form Message</h1>
                        </td>
                    </tr>
                </table>

                <!-- Content -->
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: white; padding: 30px;">
                    <tr>
                        <td>
                            <!-- Sender Info -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                                <tr>
                                    <td>
                                        <table width="100%" cellpadding="5" cellspacing="0">
                                            <tr>
                                                <td style="color: #6b7280; font-size: 14px; width: 100px;">From:</td>
                                                <td style="color: #111827; font-size: 14px; font-weight: bold;">{$name}</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #6b7280; font-size: 14px;">Email:</td>
                                                <td style="color: #7f22ea; font-size: 14px;"><a href="mailto:{$email}" style="color: #7f22ea;">{$email}</a></td>
                                            </tr>
                                            <tr>
                                                <td style="color: #6b7280; font-size: 14px;">Subject:</td>
                                                <td style="color: #111827; font-size: 14px;">{$subject}</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #6b7280; font-size: 14px;">Date:</td>
                                                <td style="color: #111827; font-size: 14px;">{$date}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Message -->
                            <h3 style="color: #111827; margin: 0 0 15px 0; font-size: 16px;">Message:</h3>
                            <div style="color: #374151; font-size: 15px; line-height: 1.6; padding: 15px; background-color: #f9fafb; border-radius: 8px; border-left: 4px solid #7f22ea;">
                                {$message}
                            </div>

                            <!-- Reply Button -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 25px;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="mailto:{$email}?subject=Re: {$subject}" style="display: inline-block; background-color: #7f22ea; color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-size: 14px; font-weight: bold;">
                                            Reply to {$name}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <!-- Footer -->
                <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f9fafb; border-radius: 0 0 12px 12px; padding: 20px; text-align: center;">
                    <tr>
                        <td>
                            <p style="color: #9ca3af; font-size: 12px; margin: 0;">
                                This message was sent from the Adoscript contact form.
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

/**
 * Build plain text email content
 */
function buildContactEmailText($name, $email, $subject, $message) {
    $date = date('F j, Y \a\t g:i A');
    
    return <<<TEXT
NEW CONTACT FORM MESSAGE
========================

From: {$name}
Email: {$email}
Subject: {$subject}
Date: {$date}

MESSAGE:
--------
{$message}

---
This message was sent from the Adoscript contact form.
Reply directly to this email to respond to {$name}.
TEXT;
}






