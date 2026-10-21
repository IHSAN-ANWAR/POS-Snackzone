-- =====================================================
-- Migration: Profit Tracking System
-- Date: 2026-10-21
-- Description: Add raw material purchase tracking
--              for profit calculation
-- =====================================================

-- Raw material purchases (calendar-day based, not shift-based)
-- This runs parallel to shift_materials for flexible tracking
CREATE TABLE IF NOT EXISTS `raw_material_purchases` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `material_name` VARCHAR(150) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `unit` VARCHAR(20) NOT NULL DEFAULT 'kg',
  `price_per_unit` DECIMAL(10,2) NOT NULL,
  `total_cost` DECIMAL(10,2) NOT NULL,
  `purchase_date` DATE NOT NULL,
  `supplier_id` INT(11) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `purchase_date` (`purchase_date`),
  KEY `supplier_id` (`supplier_id`),
  KEY `material_name` (`material_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for fast date range queries
CREATE INDEX idx_purchase_date_cost ON raw_material_purchases(purchase_date, total_cost);

-- =====================================================
-- Note: Existing shift_materials table remains unchanged
-- Both systems can coexist:
-- - shift_materials: For shift-based tracking (3pm-3pm)
-- - raw_material_purchases: For calendar-day profit reports
-- =====================================================
