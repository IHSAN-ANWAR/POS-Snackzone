-- SnackZone Database Setup
-- Run this in phpMyAdmin on database: u896451268_snackzone

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── USERS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `name`           VARCHAR(100) NOT NULL,
  `phone`          VARCHAR(20)  NOT NULL,
  `email`          VARCHAR(150) NOT NULL UNIQUE,
  `email_verified` TINYINT(1)   DEFAULT 0,
  `email_otp`      VARCHAR(6)   DEFAULT '',
  `otp_expires`    DATETIME     DEFAULT NULL,
  `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── CATEGORIES ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `slug`       VARCHAR(100) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── MENU ITEMS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `category`    VARCHAR(50)   NOT NULL,
  `title`       VARCHAR(150)  NOT NULL,
  `description` TEXT          NOT NULL,
  `price`       DECIMAL(10,2) NOT NULL,
  `image_path`  VARCHAR(255)  NOT NULL DEFAULT '',
  `tag`         VARCHAR(50)   NOT NULL DEFAULT '',
  `created_at`  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ORDERS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100)  NOT NULL,
  `phone`      VARCHAR(20)   NOT NULL,
  `address`    TEXT          NOT NULL,
  `items`      TEXT          NOT NULL,
  `total`      DECIMAL(10,2) DEFAULT 0,
  `notes`      TEXT          DEFAULT '',
  `status`     VARCHAR(20)   DEFAULT 'pending',
  `created_at` TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── CONTACT MESSAGES ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `phone`      VARCHAR(20)  DEFAULT '',
  `email`      VARCHAR(150) DEFAULT '',
  `subject`    VARCHAR(100) NOT NULL,
  `message`    TEXT         NOT NULL,
  `is_read`    TINYINT(1)   DEFAULT 0,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── EVENT BOOKINGS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `event_bookings` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(100) NOT NULL,
  `phone`      VARCHAR(20)  NOT NULL,
  `email`      VARCHAR(150) DEFAULT '',
  `event_date` DATE         NOT NULL,
  `event_time` VARCHAR(20)  NOT NULL,
  `guests`     INT          NOT NULL,
  `menu_items` TEXT         NOT NULL,
  `notes`      TEXT         DEFAULT '',
  `is_read`    TINYINT(1)   DEFAULT 0,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── ADMIN USER (login: admin@snackzone.food / Snackzone2026) ──
CREATE TABLE IF NOT EXISTS `admins` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(150) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
