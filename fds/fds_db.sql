
CREATE DATABASE IF NOT EXISTS `fds_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fds_db`;
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `type` ENUM('meal', 'drink') NOT NULL,
  `is_available` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` VARCHAR(50) NOT NULL UNIQUE,
  `customer_name` VARCHAR(150) NOT NULL,
  `student_id` VARCHAR(50) NOT NULL,
  `order_type` VARCHAR(30) NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `total_qty` INT NOT NULL,
  `grand_total` DECIMAL(10, 2) NOT NULL,
  `prep_time` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('pending', 'preparing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` VARCHAR(50) NOT NULL,
  `item_code` VARCHAR(50) NOT NULL,
  `item_name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `quantity` INT NOT NULL,
  `subtotal` DECIMAL(10, 2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) DEFAULT 'SCC Administrator',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_name` VARCHAR(150) NOT NULL,
  `student_id` VARCHAR(50) NOT NULL UNIQUE,
  `last_login` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `admins` (`username`, `password`, `full_name`) VALUES
('admin', '$2y$10$wE3x1G9Fp40oYn0l.l95/OM6H5gM0pT3P3C31H0cQ3U7G6qjB2Lq2', 'SCC Canteen Administrator')
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`);

INSERT INTO `menu_items` (`item_code`, `name`, `price`, `type`, `is_available`) VALUES
('pastil', 'Chicken pastil w/ Rice', 20.00, 'meal', 1),
('siomai', 'Beff siomai  w/ Rice', 25.00, 'meal', 1),
('Fried Chicken', 'Fried Chicken', 45.00, 'meal', 1),
('pancit', 'Pancit Canton', 35.00, 'meal', 1),
('lumpia', 'Lumpiang Shanghai w/ Rice', 45.00, 'meal', 1),
('buko', 'Buko Juice', 25.00, 'drink', 1),
('icedtea', 'Iced Tea', 25.00, 'drink', 1),
('Coke', 'Coke (in can)', 25.00, 'drink', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `price`=VALUES(`price`), `type`=VALUES(`type`);

