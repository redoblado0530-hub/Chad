<?php
// SL Eats - Admin Panel: Product & Menu Management
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Require admin login
if (empty($_SESSION['admin_logged_in'])) {
  header('Location: login.php?role=admin&error=' . urlencode('Please log in with admin username and password.'));
  exit;
}

require_once __DIR__ . '/db.php';

$message = '';
$error = '';

// Handle status query parameters
if (isset($_GET['msg'])) {
  switch ($_GET['msg']) {
    case 'added':
      $message = 'Product added successfully!';
      break;
    case 'updated':
      $message = 'Product updated successfully!';
      break;
    case 'deleted':
      $message = 'Product deleted successfully!';
      break;
    case 'status_changed':
      $message = 'Product availability updated!';
      break;
  }
}
if (isset($_GET['error'])) {
  $error = htmlspecialchars($_GET['error']);
}

// Helper function to generate clean unique item code
function generateItemCode($conn, $name, $exclude_id = 0) {
  $base = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($name)));
  $base = trim($base, '_');
  if ($base === '') {
    $base = 'item';
  }
  $code = $base;
  $i = 1;
  while (true) {
    $stmt = $conn->prepare("SELECT id FROM `menu_items` WHERE `item_code` = ? AND id != ?");
    $stmt->bind_param("si", $code, $exclude_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
      $stmt->close();
      return $code;
    }
    $stmt->close();
    $code = $base . '_' . $i;
    $i++;
  }
}

// -------------------------------------------------------------
// POST Actions: Add, Update, Toggle Status, Delete
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = isset($_POST['action']) ? trim($_POST['action']) : '';

  // 1. ADD PRODUCT
  if ($action === 'add') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $type = isset($_POST['type']) && $_POST['type'] === 'drink' ? 'drink' : 'meal';
    $is_available = isset($_POST['is_available']) ? (int)$_POST['is_available'] : 1;
    $custom_code = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';

    if ($name === '') {
      header('Location: admin.php?error=' . urlencode('Product name is required.'));
      exit;
    }
    if ($price <= 0) {
      header('Location: admin.php?error=' . urlencode('Please enter a valid price greater than 0.'));
      exit;
    }

    // Determine item code
    if ($custom_code !== '') {
      $code = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '', $custom_code));
      // Check for code uniqueness
      $check = $conn->prepare("SELECT id FROM `menu_items` WHERE `item_code` = ?");
      $check->bind_param("s", $code);
      $check->execute();
      if ($check->get_result()->num_rows > 0) {
        $check->close();
        header('Location: admin.php?error=' . urlencode("Item code '{$code}' already exists. Please choose another code."));
        exit;
      }
      $check->close();
    } else {
      $code = generateItemCode($conn, $name);
    }

    $stmt = $conn->prepare("INSERT INTO `menu_items` (`item_code`, `name`, `price`, `type`, `is_available`) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
      $stmt->bind_param("ssdsi", $code, $name, $price, $type, $is_available);
      if ($stmt->execute()) {
        $stmt->close();
        header('Location: admin.php?msg=added');
        exit;
      } else {
        $err = $stmt->error;
        $stmt->close();
        header('Location: admin.php?error=' . urlencode('Failed to add product: ' . $err));
        exit;
      }
    }
  }

  // 2. UPDATE PRODUCT
  if ($action === 'update') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $type = isset($_POST['type']) && $_POST['type'] === 'drink' ? 'drink' : 'meal';
    $is_available = isset($_POST['is_available']) ? (int)$_POST['is_available'] : 1;
    $custom_code = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';

    if ($id <= 0 || $name === '') {
      header('Location: admin.php?error=' . urlencode('Product ID and Name are required.'));
      exit;
    }
    if ($price <= 0) {
      header('Location: admin.php?error=' . urlencode('Please enter a valid price greater than 0.'));
      exit;
    }

    if ($custom_code !== '') {
      $code = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '', $custom_code));
      $check = $conn->prepare("SELECT id FROM `menu_items` WHERE `item_code` = ? AND id != ?");
      $check->bind_param("si", $code, $id);
      $check->execute();
      if ($check->get_result()->num_rows > 0) {
        $check->close();
        header("Location: admin.php?edit={$id}&error=" . urlencode("Item code '{$code}' is already used by another item."));
        exit;
      }
      $check->close();
    } else {
      $code = generateItemCode($conn, $name, $id);
    }

    $stmt = $conn->prepare("UPDATE `menu_items` SET `item_code` = ?, `name` = ?, `price` = ?, `type` = ?, `is_available` = ? WHERE `id` = ?");
    if ($stmt) {
      $stmt->bind_param("ssdsii", $code, $name, $price, $type, $is_available, $id);
      if ($stmt->execute()) {
        $stmt->close();
        header('Location: admin.php?msg=updated');
        exit;
      } else {
        $err = $stmt->error;
        $stmt->close();
        header("Location: admin.php?edit={$id}&error=" . urlencode('Failed to update product: ' . $err));
        exit;
      }
    }
  }

  // 3. TOGGLE AVAILABILITY
  if ($action === 'toggle') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
      $stmt = $conn->prepare("UPDATE `menu_items` SET `is_available` = IF(`is_available` = 1, 0, 1) WHERE `id` = ?");
      if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header('Location: admin.php?msg=status_changed');
        exit;
      }
    }
    header('Location: admin.php');
    exit;
  }

  // 4. DELETE PRODUCT
  if ($action === 'delete') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
      $stmt = $conn->prepare("DELETE FROM `menu_items` WHERE `id` = ?");
      if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header('Location: admin.php?msg=deleted');
        exit;
      }
    }
    header('Location: admin.php');
    exit;
  }
}

// Check if an item is being edited
$edit_item = null;
if (isset($_GET['edit'])) {
  $edit_id = (int)$_GET['edit'];
  if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM `menu_items` WHERE `id` = ?");
    if ($stmt) {
      $stmt->bind_param("i", $edit_id);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($res->num_rows > 0) {
        $edit_item = $res->fetch_assoc();
      }
      $stmt->close();
    }
  }
}

// Fetch all menu items
$menu_items = [];
$res = $conn->query("SELECT * FROM `menu_items` ORDER BY FIELD(`type`, 'meal', 'drink'), `id` ASC");
if ($res && $res->num_rows > 0) {
  while ($row = $res->fetch_assoc()) {
    $menu_items[] = $row;
  }
}

// Calculate summary counts
$total_products = count($menu_items);
$total_meals = 0;
$total_drinks = 0;
$total_available = 0;
foreach ($menu_items as $item) {
  if ($item['type'] === 'meal') $total_meals++;
  if ($item['type'] === 'drink') $total_drinks++;
  if ((int)$item['is_available'] === 1) $total_available++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Manage Products | SL Eats</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
      margin-bottom: 18px;
    }
    .stat-card {
      background: white;
      border: 1px solid #dfe5da;
      border-radius: 8px;
      padding: 14px;
      text-align: center;
    }
    .stat-card .num {
      font-size: 26px;
      font-weight: bold;
      color: #7d04a1;
      margin-top: 4px;
    }
    .action-group {
      display: flex;
      gap: 6px;
      align-items: center;
      flex-wrap: wrap;
    }
    .action-group form {
      margin: 0;
      display: inline-block;
    }
  </style>
</head>
<body>
  <header>
    <div class="scc-badge">sibonga community college</div>
    <h1> 🍚SL Eats🥤 - Admin Panel</h1>
    <p>Campus Foods Product & Menu Management &bull; SCC Canteen</p>
  </header>

  <div class="container">
    <div class="user-bar">
      <div class="user-info">
        🛡️ Admin: <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>
        <span style="color: #666; font-size: 13px;">(<?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator'); ?>)</span>
      </div>
      <div style="display: flex; gap: 8px; align-items: center;">
        <a href="view_orders.php" class="btn btn-sm" style="background: #1565c0;">📋 View Orders</a>
        <a href="logout.php?role=admin" class="logout-btn"><button>🚪 Logout</button></a>
      </div>
    </div>
    <?php if ($message): ?>
      <div class="success">🎉 <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="error">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Summary Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div>Total Products</div>
        <div class="num"><?php echo $total_products; ?></div>
      </div>
      <div class="stat-card">
        <div>🍚 Meals</div>
        <div class="num"><?php echo $total_meals; ?></div>
      </div>
      <div class="stat-card">
        <div>🥤 Drinks</div>
        <div class="num"><?php echo $total_drinks; ?></div>
      </div>
      <div class="stat-card">
        <div>🟢 In Stock</div>
        <div class="num"><?php echo $total_available; ?></div>
      </div>
    </div>

    <!-- Add / Edit Product Form -->
    <div class="card" id="productForm">
      <h2><?php echo $edit_item ? '✏️ Edit Product: ' . htmlspecialchars($edit_item['name']) : '➕ Add New Product'; ?></h2>

      <form method="post" action="admin.php">
        <input type="hidden" name="action" value="<?php echo $edit_item ? 'update' : 'add'; ?>">
        <?php if ($edit_item): ?>
          <input type="hidden" name="id" value="<?php echo (int)$edit_item['id']; ?>">
        <?php endif; ?>

        <div class="form-grid">
          <div>
            <label for="name">Product Name *</label>
            <input type="text" id="name" name="name" placeholder="e.g. Pork Sisig w/ Rice" 
                   value="<?php echo $edit_item ? htmlspecialchars($edit_item['name']) : ''; ?>" required>
          </div>

          <div>
            <label for="price">Price (₱) *</label>
            <input type="number" step="0.01" min="1" id="price" name="price" class="form-control" placeholder="e.g. 50.00" 
                   value="<?php echo $edit_item ? htmlspecialchars($edit_item['price']) : ''; ?>" required>
          </div>

          <div>
            <label for="type">Category *</label>
            <select id="type" name="type" required>
              <option value="meal" <?php echo ($edit_item && $edit_item['type'] === 'meal') ? 'selected' : ''; ?>>🍚 Meal</option>
              <option value="drink" <?php echo ($edit_item && $edit_item['type'] === 'drink') ? 'selected' : ''; ?>>🥤 Drink</option>
            </select>
          </div>
        </div>

        <div class="form-grid">
          <div>
            <label for="is_available">Stock Availability</label>
            <select id="is_available" name="is_available">
              <option value="1" <?php echo (!$edit_item || (int)$edit_item['is_available'] === 1) ? 'selected' : ''; ?>>🟢 Available (In Stock)</option>
              <option value="0" <?php echo ($edit_item && (int)$edit_item['is_available'] === 0) ? 'selected' : ''; ?>>🔴 Unavailable (Out of Stock)</option>
            </select>
          </div>

          <div>
            <label for="item_code">Item Code / Slug <span style="font-weight: normal; color: #777;">(Optional - auto-generated if blank)</span></label>
            <input type="text" id="item_code" name="item_code" placeholder="e.g. pork_sisig" 
                   value="<?php echo $edit_item ? htmlspecialchars($edit_item['item_code']) : ''; ?>">
          </div>
        </div>

        <div style="display: flex; gap: 10px; align-items: center; margin-top: 6px;">
          <button type="submit" class="btn">
            <?php echo $edit_item ? '💾 Save Changes' : '➕ Add Product'; ?>
          </button>
          <?php if ($edit_item): ?>
            <a href="admin.php" class="btn btn-secondary">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Product List Table -->
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
        <h2>Current Menu Products (<?php echo count($menu_items); ?>)</h2>
        <a href="#productForm" class="btn btn-sm" style="font-size: 13px;">+ Add Another Product</a>
      </div>

      <?php if (empty($menu_items)): ?>
        <p style="text-align: center; color: #777; padding: 25px;">No products in menu yet. Use the form above to add your first product!</p>
      <?php else: ?>
        <div style="overflow-x: auto;">
          <table>
            <thead>
              <tr>
                <th style="width: 50px;">ID</th>
                <th>Product Name</th>
                <th>Category</th>
                <th class="right">Price</th>
                <th>Status</th>
                <th style="width: 140px;">Item Code</th>
                <th style="width: 220px;" class="right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($menu_items as $prod): ?>
                <tr>
                  <td><?php echo (int)$prod['id']; ?></td>
                  <td><strong><?php echo htmlspecialchars($prod['name']); ?></strong></td>
                  <td>
                    <?php if ($prod['type'] === 'meal'): ?>
                      <span class="badge badge-meal">🍚 Meal</span>
                    <?php else: ?>
                      <span class="badge badge-drink">🥤 Drink</span>
                    <?php endif; ?>
                  </td>
                  <td class="right" style="font-weight: bold; color: #c62828;">
                    ₱<?php echo number_format($prod['price'], 2); ?>
                  </td>
                  <td>
                    <?php if ((int)$prod['is_available'] === 1): ?>
                      <span class="badge badge-available">🟢 In Stock</span>
                    <?php else: ?>
                      <span class="badge badge-unavailable">🔴 Out of Stock</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-family: monospace; font-size: 13px; color: #555;">
                    <?php echo htmlspecialchars($prod['item_code']); ?>
                  </td>
                  <td class="right">
                    <div class="action-group" style="justify-content: flex-end;">
                      <!-- Quick Toggle Availability -->
                      <form method="post" action="admin.php">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?php echo (int)$prod['id']; ?>">
                        <button type="submit" class="btn btn-sm <?php echo (int)$prod['is_available'] === 1 ? 'btn-warning' : 'btn-success'; ?>" 
                                title="Toggle stock availability">
                          <?php echo (int)$prod['is_available'] === 1 ? 'Mark Out' : 'Mark In'; ?>
                        </button>
                      </form>

                      <!-- Edit Button -->
                      <a href="admin.php?edit=<?php echo (int)$prod['id']; ?>#productForm" class="btn btn-sm" style="background: #1565c0;" title="Edit product">
                        ✏️ Edit
                      </a>

                      <!-- Delete Button -->
                      <form method="post" action="admin.php" onsubmit="return confirm('Are you sure you want to delete \'<?php echo addslashes($prod['name']); ?>\'?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$prod['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger" title="Delete product">
                          🗑️ Delete
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <footer>
    SL Eats &bull; SIBONGA COMMUNITY COLLEGE (SCC) &bull; A project by BSIT 2I &bull; &copy; <?php echo date('Y'); ?>
  </footer>
</body>
</html>

