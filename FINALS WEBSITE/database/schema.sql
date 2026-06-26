CREATE DATABASE IF NOT EXISTS `login_credentials`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS `main`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `login_credentials`;

CREATE TABLE IF NOT EXISTS `employee_credentials` (
  `employee_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `roles` VARCHAR(20) NOT NULL DEFAULT 'staff',
  `datejoined` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `uq_employee_username` (`username`),
  UNIQUE KEY `uq_employee_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

USE `main`;

CREATE TABLE IF NOT EXISTS `products` (
  `product_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_name` VARCHAR(150) NOT NULL,
  `variant` VARCHAR(80) NOT NULL DEFAULT '',
  `category` VARCHAR(80) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `threshold` INT NOT NULL DEFAULT 10,
  `emoji` VARCHAR(16) DEFAULT '☕',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `uq_products_name_variant` (`product_name`, `variant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `addons` (
  `slug` VARCHAR(80) NOT NULL,
  `label` VARCHAR(120) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ingredients` (
  `ingredient_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ingredient_name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(80) NOT NULL,
  `unit_of_measure` VARCHAR(20) NOT NULL DEFAULT 'g',
  `current_quantity` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ingredient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `order_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_ref` VARCHAR(30) NOT NULL,
  `cashier_id` VARCHAR(64) NOT NULL,
  `customer_first` VARCHAR(100) DEFAULT NULL,
  `customer_last` VARCHAR(100) DEFAULT NULL,
  `customer_contact` VARCHAR(50) DEFAULT NULL,
  `customer_address` VARCHAR(255) DEFAULT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount_type` VARCHAR(40) NOT NULL DEFAULT 'none',
  `discount_value` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `discount_id_number` VARCHAR(80) DEFAULT NULL,
  `discount_holder_name` VARCHAR(150) DEFAULT NULL,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `cash_tendered` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `change_given` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uq_orders_order_ref` (`order_ref`),
  KEY `idx_orders_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
  `order_item_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `addon_total` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `quantity` INT NOT NULL DEFAULT 1,
  `line_total` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `special_note` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_item_id`),
  KEY `idx_order_items_order_id` (`order_id`),
  CONSTRAINT `fk_order_items_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_item_addons` (
  `order_item_addon_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_item_id` INT UNSIGNED NOT NULL,
  `addon_slug` VARCHAR(80) NOT NULL,
  `addon_label` VARCHAR(120) NOT NULL,
  `addon_price` DECIMAL(10,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (`order_item_addon_id`),
  KEY `idx_order_item_addons_item` (`order_item_id`),
  CONSTRAINT `fk_order_item_addons_item`
    FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`order_item_id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` VARCHAR(64) DEFAULT NULL,
  `user_name` VARCHAR(120) DEFAULT NULL,
  `type` VARCHAR(40) NOT NULL,
  `action` VARCHAR(120) NOT NULL,
  `detail` TEXT,
  `ref` VARCHAR(60) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_activity_type_created` (`type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
