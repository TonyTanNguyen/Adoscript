<?php
/**
 * Database Setup Script
 * Run this once to create/update database tables
 *
 * Usage: php api/setup-db.php
 * Or visit: /api/setup-db.php?run=1
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/db.php';

// Security check - only run if explicitly requested
if (php_sapi_name() !== 'cli' && !isset($_GET['run']) && !isset($_GET['reset-password'])) {
    die('Add ?run=1 to URL to execute database setup, or ?reset-password=1 to reset admin password');
}

// Handle password reset
if (isset($_GET['reset-password'])) {
    try {
        $db = getDB();
        $defaultEmail = defined('DEFAULT_ADMIN_EMAIL') ? DEFAULT_ADMIN_EMAIL : 'admin@adoscript.com';
        $defaultPassword = defined('DEFAULT_ADMIN_PASSWORD') ? DEFAULT_ADMIN_PASSWORD : 'admin123';

        $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([password_hash($defaultPassword, PASSWORD_DEFAULT), $defaultEmail]);

        if ($stmt->rowCount() > 0) {
            echo "Password reset successful!<br>";
            echo "Email: " . htmlspecialchars($defaultEmail) . "<br>";
            echo "Password: " . htmlspecialchars($defaultPassword) . "<br><br>";
            echo "<strong>Please change your password after logging in!</strong><br><br>";
            echo "<a href='../admin/login.html'>Go to Login</a>";
        } else {
            echo "No user found with email: " . htmlspecialchars($defaultEmail);
        }
        exit;
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

try {
    $db = getDB();
    echo "Connected to database.\n";

    // Create scripts table
    $db->exec("
        CREATE TABLE IF NOT EXISTS scripts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            application VARCHAR(50) NOT NULL,
            version VARCHAR(20) DEFAULT '1.0.0',
            short_description TEXT,
            full_description TEXT,
            installation_instructions TEXT,
            usage_instructions TEXT,
            system_requirements TEXT,
            compatibility TEXT,
            tags TEXT,
            changelog TEXT,
            price_type VARCHAR(20) DEFAULT 'free',
            price DECIMAL(10,2) DEFAULT 0,
            file_path VARCHAR(255),
            file_size VARCHAR(50),
            downloads INTEGER DEFAULT 0,
            status VARCHAR(20) DEFAULT 'draft',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "Scripts table ready.\n";

    // Create script_images table
    $db->exec("
        CREATE TABLE IF NOT EXISTS script_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            script_id INTEGER NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            display_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (script_id) REFERENCES scripts(id) ON DELETE CASCADE
        )
    ");
    echo "Script images table ready.\n";

    // Create script_videos table
    $db->exec("
        CREATE TABLE IF NOT EXISTS script_videos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            script_id INTEGER NOT NULL,
            video_url VARCHAR(500) NOT NULL,
            title VARCHAR(255),
            display_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (script_id) REFERENCES scripts(id) ON DELETE CASCADE
        )
    ");
    echo "Script videos table ready.\n";

    // Create orders table with PayPal support
    $db->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id VARCHAR(255) UNIQUE,
            script_id INTEGER NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_name VARCHAR(255),
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(3) DEFAULT 'USD',
            payment_method VARCHAR(50) DEFAULT 'paypal',
            payment_id VARCHAR(255),
            transaction_id VARCHAR(255),
            status VARCHAR(50) DEFAULT 'pending',
            download_token VARCHAR(64),
            token_expires_at DATETIME,
            download_count INTEGER DEFAULT 0,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (script_id) REFERENCES scripts(id)
        )
    ");
    echo "Orders table ready.\n";

    // Create users table for admin
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            role VARCHAR(50) DEFAULT 'admin',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "Users table ready.\n";

    // Check if admin user exists, if not create default
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $defaultEmail = defined('DEFAULT_ADMIN_EMAIL') ? DEFAULT_ADMIN_EMAIL : 'admin@adoscript.com';
        $defaultPassword = defined('DEFAULT_ADMIN_PASSWORD') ? DEFAULT_ADMIN_PASSWORD : 'admin123';

        $stmt = $db->prepare("INSERT INTO users (email, password, name, role) VALUES (?, ?, 'Admin', 'admin')");
        $stmt->execute([$defaultEmail, password_hash($defaultPassword, PASSWORD_DEFAULT)]);
        echo "Default admin user created.\n";
    }

    // Add missing columns to orders table if they don't exist (for existing databases)
    $columns = $db->query("PRAGMA table_info(orders)")->fetchAll(PDO::FETCH_COLUMN, 1);

    $newColumns = [
        'order_id' => "ALTER TABLE orders ADD COLUMN order_id VARCHAR(255)",
        'payment_method' => "ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'paypal'",
        'payment_id' => "ALTER TABLE orders ADD COLUMN payment_id VARCHAR(255)",
        'transaction_id' => "ALTER TABLE orders ADD COLUMN transaction_id VARCHAR(255)",
        'download_token' => "ALTER TABLE orders ADD COLUMN download_token VARCHAR(64)",
        'token_expires_at' => "ALTER TABLE orders ADD COLUMN token_expires_at DATETIME",
        'download_count' => "ALTER TABLE orders ADD COLUMN download_count INTEGER DEFAULT 0",
        'currency' => "ALTER TABLE orders ADD COLUMN currency VARCHAR(3) DEFAULT 'USD'",
        'updated_at' => "ALTER TABLE orders ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP"
    ];

    foreach ($newColumns as $column => $sql) {
        if (!in_array($column, $columns)) {
            $db->exec($sql);
            echo "Added column '$column' to orders table.\n";
        }
    }

    // Add missing columns to users table if they don't exist (for existing databases)
    $userColumns = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_COLUMN, 1);

    $newUserColumns = [
        'role' => "ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'admin'",
        'updated_at' => "ALTER TABLE users ADD COLUMN updated_at DATETIME"
    ];

    foreach ($newUserColumns as $column => $sql) {
        if (!in_array($column, $userColumns)) {
            $db->exec($sql);
            echo "Added column '$column' to users table.\n";
        }
    }

    echo "\nDatabase setup complete!\n";

    if (php_sapi_name() !== 'cli') {
        echo "<br><br><a href='../admin/dashboard.html'>Go to Admin Dashboard</a>";
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}
