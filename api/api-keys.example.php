<?php
/**
 * API Keys Configuration
 *
 * Copy this file to 'api-keys.php' and add your actual API keys.
 * The api-keys.php file is git-ignored for security.
 */

// Claude API Key
// Get your API key from: https://console.anthropic.com/
define('CLAUDE_API_KEY', 'sk-ant-api03-your-key-here');

// PayPal Configuration
// Get your credentials from: https://developer.paypal.com/dashboard/applications
// Use sandbox credentials for testing, live credentials for production
define('PAYPAL_CLIENT_ID', 'your-paypal-client-id-here');
define('PAYPAL_CLIENT_SECRET', 'your-paypal-client-secret-here');
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' for testing, 'live' for production

// Email Configuration (SMTP)
// ========================================
// For Hostinger: smtp.hostinger.com, port 465 (SSL) or 587 (TLS)
// For Gmail: smtp.gmail.com, port 587, use App Password
// For Outlook: smtp.office365.com, port 587
// ========================================

// Hostinger Email Configuration (recommended for @adoscript.com)
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);                              // Use 465 for SSL (recommended) or 587 for TLS
define('SMTP_USERNAME', 'noreply@adoscript.com');      // Your full Hostinger email address
define('SMTP_PASSWORD', 'your-email-password');         // Your Hostinger email password
define('SMTP_FROM_EMAIL', 'noreply@adoscript.com');    // From email address
define('SMTP_FROM_NAME', 'Adoscript');                 // From display name
