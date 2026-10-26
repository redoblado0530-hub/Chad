<?php
// Database configuration and connection for SL Eats (fds)
mysqli_report(MYSQLI_REPORT_OFF);

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'fds_db';

// Initial connection to MySQL server
$conn = null;
try {
  $conn = @new mysqli($db_host, $db_user, $db_pass);
} catch (Throwable $e) {
  $conn = null;
}

if (!$conn || $conn->connect_error) {
  $error_msg = $conn ? $conn->connect_error : 'Connection refused. Please start MySQL in the XAMPP Control Panel.';
  die('<!DOCTYPE html>
  <html>
  <head><title>Database Connection Error</title><link rel="stylesheet" href="style.css"></head>
  <body>
    <div class="container" style="margin-top: 50px;">
      <div class="card" style="text-align: center; border-color: #f5c6cb;">
        <h2 style="color: #c62828;">⚠️ Database Connection Error</h2>
        <p style="margin: 15px 0;">Could not connect to the MySQL database server.</p>
        <div class="error" style="text-align: left; font-family: monospace;">
          ' . htmlspecialchars($error_msg) . '
        </div>
        <p style="color: #555; font-size: 14px;">
          <strong>Tip:</strong> Open the <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> next to <strong>MySQL</strong>.
        </p>
      </div>
    </div>
  </body>
  </html>');
}

// Ensure database exists
$conn->query("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($db_name);

// Auto-create tables if they do not exist
$table_menu = "CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `type` ENUM('meal', 'drink') NOT NULL,
  `is_available` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$table_orders = "CREATE TABLE IF NOT EXISTS `orders` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$table_items = "CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` VARCHAR(50) NOT NULL,
  `item_code` VARCHAR(50) NOT NULL,
  `item_name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10, 2) NOT NULL,
  `quantity` INT NOT NULL,
  `subtotal` DECIMAL(10, 2) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// 4. Admin Users Table
$table_admins = "CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) DEFAULT 'SCC Administrator',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

// 5. Students Log Table
$table_students = "CREATE TABLE IF NOT EXISTS `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_name` VARCHAR(150) NOT NULL,
  `student_id` VARCHAR(50) NOT NULL UNIQUE,
  `last_login` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

$conn->query($table_menu);
$conn->query($table_orders);
$conn->query($table_items);
$conn->query($table_admins);
$conn->query($table_students);

// Check if admins table needs default sample admin
$check_admin = $conn->query("SELECT COUNT(*) AS total FROM `admins`");
$admin_count = $check_admin ? (int)$check_admin->fetch_assoc()['total'] : 0;
if ($admin_count === 0) {
  $default_pass = password_hash('admin123', PASSWORD_DEFAULT);
  $seed_admin = $conn->prepare("INSERT INTO `admins` (`username`, `password`, `full_name`) VALUES (?, ?, ?)");
  if ($seed_admin) {
    $uname = 'admin';
    $fname = 'SCC Canteen Administrator';
    $seed_admin->bind_param("sss", $uname, $default_pass, $fname);
    $seed_admin->execute();
    $seed_admin->close();
  }
}

// Check if menu_items needs default seed data
$check_menu = $conn->query("SELECT COUNT(*) AS total FROM `menu_items`");
$count = $check_menu ? (int)$check_menu->fetch_assoc()['total'] : 0;

if ($count === 0) {
  $seed_sql = "INSERT INTO `menu_items` (`item_code`, `name`, `price`, `type`, `is_available`) VALUES
    ('pastil', 'Chicken pastil w/ Rice', 20.00, 'meal', 1),
    ('siomai', 'Beff siomai  w/ Rice', 25.00, 'meal', 1),
    ('Fried Chicken', 'Fried Chicken', 45.00, 'meal', 1),
    ('pancit', 'Pancit Canton', 35.00, 'meal', 1),
    ('lumpia', 'Lumpiang Shanghai w/ Rice', 45.00, 'meal', 1),
    ('buko', 'Buko Juice', 25.00, 'drink', 1),
    ('icedtea', 'Iced Tea', 25.00, 'drink', 1),
    ('Coke', 'Coke (in can)', 25.00, 'drink', 1);";
  $conn->query($seed_sql);
}

// For compatibility with scripts using $mysqli
$mysqli = $conn;
?>

