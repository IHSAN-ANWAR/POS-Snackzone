<?php
/**
 * Run this ONCE after deployment to set up / update the database schema.
 * Access: http://yourdomain.com/Admin/migrate.php
 * Delete or protect this file after running.
 *
 * SECURITY: Requires admin session. Access from command-line by setting
 * MIGRATE_CLI=1 environment variable: php migrate.php
 */

// Allow CLI execution without session (for deployment scripts)
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    session_start();
    if (!isset($_SESSION['snackzone_admin'])) {
        http_response_code(403);
        die('Access denied. Admin login required to run migrations.');
    }
}

require 'config.php';
$conn = getDB();

$results = [];

// ── Table creation ────────────────────────────────────────
$migrations = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        name           VARCHAR(100) NOT NULL,
        phone          VARCHAR(20)  NOT NULL,
        email          VARCHAR(150) NOT NULL UNIQUE,
        email_verified TINYINT(1)   DEFAULT 0,
        email_otp      VARCHAR(6)   DEFAULT '',
        otp_expires    DATETIME     DEFAULT NULL,
        created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "categories" => "CREATE TABLE IF NOT EXISTS categories (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        name         VARCHAR(100) NOT NULL,
        slug         VARCHAR(100) NOT NULL UNIQUE,
        banner_image VARCHAR(255) DEFAULT '',
        created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "menu_items" => "CREATE TABLE IF NOT EXISTS menu_items (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        category    VARCHAR(50)   NOT NULL,
        title       VARCHAR(150)  NOT NULL,
        description TEXT          NOT NULL,
        price       DECIMAL(10,2) NOT NULL,
        image_path  VARCHAR(255)  NOT NULL DEFAULT '',
        tag         VARCHAR(50)   NOT NULL DEFAULT '',
        featured    TINYINT(1)    NOT NULL DEFAULT 0,
        created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "orders" => "CREATE TABLE IF NOT EXISTS orders (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT           DEFAULT NULL,
        name       VARCHAR(100)  NOT NULL,
        phone      VARCHAR(20)   NOT NULL,
        address    TEXT          NOT NULL,
        items      TEXT          NOT NULL,
        total      DECIMAL(10,2) DEFAULT 0,
        notes      TEXT          DEFAULT '',
        status     ENUM('pending','confirmed','dispatched','cancelled') NOT NULL DEFAULT 'pending',
        prep_time  VARCHAR(50)   DEFAULT '',
        created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "contact_messages" => "CREATE TABLE IF NOT EXISTS contact_messages (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        phone      VARCHAR(20)  DEFAULT '',
        email      VARCHAR(150) DEFAULT '',
        subject    VARCHAR(100) NOT NULL,
        message    TEXT         NOT NULL,
        is_read    TINYINT(1)   DEFAULT 0,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "event_bookings" => "CREATE TABLE IF NOT EXISTS event_bookings (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        phone      VARCHAR(20)  NOT NULL,
        email      VARCHAR(150) DEFAULT '',
        event_date DATE         NOT NULL,
        event_time VARCHAR(20)  NOT NULL,
        guests     INT          NOT NULL,
        menu_items TEXT         NOT NULL,
        notes      TEXT         DEFAULT '',
        is_read    TINYINT(1)   DEFAULT 0,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "notices" => "CREATE TABLE IF NOT EXISTS notices (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        title      VARCHAR(200) NOT NULL,
        body       TEXT         NOT NULL,
        image_path VARCHAR(255) DEFAULT '',
        is_active  TINYINT(1)   DEFAULT 1,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "otp_codes" => "CREATE TABLE IF NOT EXISTS otp_codes (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        phone      VARCHAR(20)  NOT NULL,
        code       VARCHAR(6)   NOT NULL,
        used       TINYINT(1)   DEFAULT 0,
        expires_at DATETIME     NOT NULL,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "admin_users" => "CREATE TABLE IF NOT EXISTS admin_users (
        id       INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(80)  NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "vendors" => "CREATE TABLE IF NOT EXISTS vendors (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        email          VARCHAR(150) NOT NULL UNIQUE,
        password       VARCHAR(255) NOT NULL,
        name           VARCHAR(100) NOT NULL,
        phone          VARCHAR(20)  NOT NULL,
        cnic           VARCHAR(20)  NOT NULL UNIQUE,
        profile_image  VARCHAR(255) DEFAULT '',
        status         ENUM('pending','approved','rejected','suspended') DEFAULT 'pending',
        created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "vendor_stores" => "CREATE TABLE IF NOT EXISTS vendor_stores (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        vendor_id         INT          NOT NULL,
        store_name        VARCHAR(200) NOT NULL,
        store_description TEXT         DEFAULT '',
        store_address     TEXT         DEFAULT '',
        store_logo        VARCHAR(255) DEFAULT '',
        is_active         TINYINT(1)   DEFAULT 1,
        created_at        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "vendor_products" => "CREATE TABLE IF NOT EXISTS vendor_products (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        vendor_id   INT           NOT NULL,
        store_id    INT           NOT NULL,
        name        VARCHAR(200)  NOT NULL,
        description TEXT          DEFAULT '',
        price       DECIMAL(10,2) NOT NULL,
        image       VARCHAR(255)  DEFAULT '',
        category    VARCHAR(100)  DEFAULT '',
        in_stock    TINYINT(1)    DEFAULT 1,
        created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (vendor_id) REFERENCES vendors(id)       ON DELETE CASCADE,
        FOREIGN KEY (store_id)  REFERENCES vendor_stores(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "menu_item_addons" => "CREATE TABLE IF NOT EXISTS menu_item_addons (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        item_id    INT           NOT NULL,
        name       VARCHAR(150)  NOT NULL,
        price      DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES menu_items(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "delivery_modal_settings" => "CREATE TABLE IF NOT EXISTS delivery_modal_settings (
        id              TINYINT(1) PRIMARY KEY DEFAULT 1,
        is_enabled      TINYINT(1) DEFAULT 1,
        pickup_location TEXT,
        updated_at      TIMESTAMP  DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "homepage_slides" => "CREATE TABLE IF NOT EXISTS homepage_slides (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(255) NOT NULL,
        label      VARCHAR(100) DEFAULT '',
        sort_order INT          DEFAULT 0,
        is_active  TINYINT(1)   DEFAULT 1,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

// ── Column additions for existing installs ────────────────
$alterations = [
    "ALTER TABLE menu_items  ADD COLUMN IF NOT EXISTS tag          VARCHAR(50)  NOT NULL DEFAULT ''",
    "ALTER TABLE menu_items  ADD COLUMN IF NOT EXISTS featured     TINYINT(1)   NOT NULL DEFAULT 0",
    "ALTER TABLE menu_items  ADD COLUMN IF NOT EXISTS discount_percent INT DEFAULT 0",
    "ALTER TABLE categories  ADD COLUMN IF NOT EXISTS banner_image VARCHAR(255) DEFAULT ''",
    "ALTER TABLE orders      ADD COLUMN IF NOT EXISTS user_id      INT          DEFAULT NULL",
    "ALTER TABLE orders      ADD COLUMN IF NOT EXISTS notes        TEXT         DEFAULT ''",
    "ALTER TABLE orders      ADD COLUMN IF NOT EXISTS prep_time    VARCHAR(50)  DEFAULT ''",
    "ALTER TABLE orders      ADD COLUMN IF NOT EXISTS total        DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE users       ADD COLUMN IF NOT EXISTS email_otp    VARCHAR(6)   DEFAULT ''",
    "ALTER TABLE users       ADD COLUMN IF NOT EXISTS otp_expires  DATETIME     DEFAULT NULL",
    // Normalize orders status column to ENUM
    "ALTER TABLE orders MODIFY COLUMN status ENUM('pending','confirmed','dispatched','cancelled') NOT NULL DEFAULT 'pending'",
    // Normalize blank statuses
    "UPDATE orders SET status='pending' WHERE status IS NULL OR status=''",
    // Normalize notes column name (rename note -> notes if old schema)
    // (silently ignored if notes already exists)
];

foreach ($migrations as $table => $sql) {
    $ok = $conn->query($sql);
    $results[] = ($ok ? '✓' : '✗ ' . $conn->error) . " Table: $table";
}

foreach ($alterations as $sql) {
    $conn->query($sql); // Errors silently ignored — column may already exist
}
$results[] = '✓ Column alterations applied';

$conn->close();

header('Content-Type: text/plain');
echo "=== Migration Complete ===\n\n";
echo implode("\n", $results);
echo "\n\nDone. Delete or protect this file after running.";
