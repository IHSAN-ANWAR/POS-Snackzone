<?php
/**
 * db.php — Schema bootstrap
 *
 * Reuses getDB() from config.php (single source of truth for the connection).
 * Run this file once to create all tables, or include it anywhere you need
 * the connection + guaranteed schema.
 *
 * Senior pattern: one connection factory, all schema in one place,
 * every query is idempotent (IF NOT EXISTS / IF NOT EXISTS).
 */

require_once __DIR__ . '/config.php';

$conn = getDB();

// ── 1. Admin users ────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS admin_users (
        id         INT          AUTO_INCREMENT PRIMARY KEY,
        username   VARCHAR(50)  NOT NULL UNIQUE,
        password   VARCHAR(255) NOT NULL,
        role       ENUM('admin','receptionist') NOT NULL DEFAULT 'admin',
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
// Ensure role column on existing installs
$conn->query("ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS role ENUM('admin','receptionist') NOT NULL DEFAULT 'admin'");

// ── 2. Menu items ─────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS menu_items (
        id          INT            AUTO_INCREMENT PRIMARY KEY,
        category    VARCHAR(50)    NOT NULL,
        title       VARCHAR(150)   NOT NULL,
        description TEXT           NOT NULL,
        price       DECIMAL(10,2)  NOT NULL,
        image_path  VARCHAR(255)   NOT NULL DEFAULT '',
        created_at  TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── 3. Contact messages ───────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS contact_messages (
        id         INT          AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        phone      VARCHAR(20)  NOT NULL DEFAULT '',
        email      VARCHAR(150) NOT NULL DEFAULT '',
        subject    VARCHAR(100) NOT NULL,
        message    TEXT         NOT NULL,
        is_read    TINYINT(1)   NOT NULL DEFAULT 0,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── 4. Bookings ───────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS bookings (
        id         INT          AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        email      VARCHAR(150) NOT NULL DEFAULT '',
        phone      VARCHAR(20)  NOT NULL DEFAULT '',
        date       DATE         NOT NULL,
        time       TIME         NOT NULL,
        guests     TINYINT      NOT NULL DEFAULT 1,
        note       TEXT,
        status     ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── 5. Orders ─────────────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS orders (
        id         INT           AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100)  NOT NULL,
        email      VARCHAR(150)  NOT NULL DEFAULT '',
        phone      VARCHAR(20)   NOT NULL DEFAULT '',
        address    TEXT          NOT NULL,
        items      JSON          NOT NULL,
        total      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status     ENUM('pending','preparing','delivered','cancelled') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── 6. Chat sessions ──────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS chat_sessions (
        id              INT          AUTO_INCREMENT PRIMARY KEY,
        order_id        INT          NOT NULL,
        guest_token     VARCHAR(64)  NOT NULL,
        customer_name   VARCHAR(100) NOT NULL DEFAULT '',
        status          ENUM('open','closed') NOT NULL DEFAULT 'open',
        last_activity   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_order (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── 7. Chat messages ──────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS chat_messages (
        id         INT          AUTO_INCREMENT PRIMARY KEY,
        session_id INT          NOT NULL,
        sender     ENUM('user','receptionist') NOT NULL,
        message    TEXT         NOT NULL,
        is_read    TINYINT(1)   NOT NULL DEFAULT 0,
        created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        KEY idx_session (session_id),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
