<?php
// SL Eats - View Orders Dashboard (MySQL)
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Require admin login
if (empty($_SESSION['admin_logged_in'])) {
  header('Location: login.php?role=admin&error=' . urlencode('Please log in with admin username and password to view orders.'));
  exit;
}

require_once __DIR__ . '/db.php';

$message = '';

// Handle status updates if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
  $order_id = isset($_POST['order_id']) ? trim($_POST['order_id']) : '';
  $new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
  $valid_statuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];

  if ($order_id !== '' && in_array($new_status, $valid_statuses, true)) {
    $update_stmt = $conn->prepare("UPDATE `orders` SET `status` = ? WHERE `order_id` = ?");
    if ($update_stmt) {
      $update_stmt->bind_param("ss", $new_status, $order_id);
      $update_stmt->execute();
      $update_stmt->close();
      $message = "Order #{$order_id} status updated to " . ucfirst($new_status) . ".";
    }
  }
}

// Fetch stats
$total_orders = 0;
$total_revenue = 0.00;
$pending_orders = 0;

$stats_res = $conn->query("SELECT 
  COUNT(*) AS total_count,
  COALESCE(SUM(grand_total), 0) AS revenue,
  SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count
FROM `orders`");

if ($stats_res && $row = $stats_res->fetch_assoc()) {
  $total_orders = (int)$row['total_count'];
  $total_revenue = (float)$row['revenue'];
  $pending_orders = (int)$row['pending_count'];
}

// Fetch all orders
$orders = [];
$orders_query = "SELECT * FROM `orders` ORDER BY `id` DESC";
$res = $conn->query($orders_query);

if ($res && $res->num_rows > 0) {
  while ($row = $res->fetch_assoc()) {
    $curr_order_id = $row['order_id'];
    
    // Fetch items for this order
    $items = [];
    $item_stmt = $conn->prepare("SELECT * FROM `order_items` WHERE `order_id` = ? ORDER BY `id` ASC");
    if ($item_stmt) {
      $item_stmt->bind_param("s", $curr_order_id);
      $item_stmt->execute();
      $item_res = $item_stmt->get_result();
      while ($item_row = $item_res->fetch_assoc()) {
        $items[] = $item_row;
      }
      $item_stmt->close();
    }
    
    $row['items'] = $items;
    $orders[] = $row;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orders Database - SL Eats</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
    }
    .stat-card {
      background: white;
      border: 1px solid #dfe5da;
      border-radius: 8px;
      padding: 16px;
      text-align: center;
    }
    .stat-card .num {
      font-size: 28px;
      font-weight: bold;
      color: #7d04a1;
      margin-top: 5px;
    }
    .badge {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 12px;
      font-weight: bold;
      text-transform: capitalize;
    }
    .badge-pending { background: #fff3e0; color: #e65100; }
    .badge-preparing { background: #e1f5fe; color: #0277bd; }
    .badge-ready { background: #e8f5e9; color: #2e7d32; }
    .badge-completed { background: #ede7f6; color: #512da8; }
    .badge-cancelled { background: #ffebee; color: #c62828; }
    .status-select {
      padding: 4px 8px;
      font-size: 12px;
      border-radius: 4px;
      border: 1px solid #bbb;
      margin-bottom: 0;
      width: auto;
      cursor: pointer;
    }
    .order-box {
      border: 1px solid #dfe5da;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 16px;
      background: #fafbfa;
    }
    .order-box-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 10px;
      border-bottom: 1px solid #e0e0e0;
      padding-bottom: 10px;
      margin-bottom: 10px;
    }
    .order-box-header .title {
      font-size: 18px;
      font-weight: bold;
      color: #1b5e20;
    }
  </style>
</head>
<body>
  <header>
    <div class="scc-badge">SIBONGA COMMUNITY COLLEGE (SCC)</div>
    <h1> 🍚SL Eats🥤 - Orders Dashboard</h1>
    <p>Campus Foods Online Ordering &bull; SCC Canteen Food Service</p>
  </header>

  <div class="container">
    <div class="user-bar">
      <div class="user-info">
        🛡️ Admin: <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>
        <span style="color: #666; font-size: 13px;">(<?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Administrator'); ?>)</span>
      </div>
      <div style="display: flex; gap: 8px; align-items: center;">
        <a href="admin.php" class="btn btn-sm">⚙️ Manage Products</a>
        <a href="logout.php?role=admin" class="logout-btn"><button>🚪 Logout</button></a>
      </div>
    </div>
    <?php if ($message): ?>
      <div class="success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="stats-grid">
      <div class="stat-card">
        <div>Total Orders</div>
        <div class="num"><?php echo $total_orders; ?></div>
      </div>
      <div class="stat-card">
        <div>Total Sales</div>
        <div class="num">₱<?php echo number_format($total_revenue, 2); ?></div>
      </div>
      <div class="stat-card">
        <div>Pending Orders</div>
        <div class="num"><?php echo $pending_orders; ?></div>
      </div>
    </div>

    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
        <h2>YOUR ORDERS</h2>
        <a href="index.php" class="btn" style="font-size: 14px; padding: 6px 14px;">+ Place New Order</a>
      </div>

      <?php if (empty($orders)): ?>
        <p style="text-align: center; color: #777; padding: 30px;">No orders found in the database yet. Place an order on the menu to see it here!</p>
      <?php else: ?>
        <?php foreach ($orders as $ord): ?>
          <div class="order-box">
            <div class="order-box-header">
              <div>
                <span class="title">#<?php echo htmlspecialchars($ord['order_id']); ?></span>
                &bull; <span style="font-weight: bold;"><?php echo htmlspecialchars($ord['customer_name']); ?></span>
                <span style="color: #666; font-size: 13px;">(ID: <?php echo htmlspecialchars($ord['student_id']); ?>)</span>
              </div>
              <div style="display: flex; align-items: center; gap: 8px;">
                <span class="badge badge-<?php echo htmlspecialchars($ord['status']); ?>">
                  <?php echo htmlspecialchars($ord['status']); ?>
                </span>
                <form method="post" action="view_orders.php" style="display: inline-block; margin: 0;">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($ord['order_id']); ?>">
                  <select name="status" class="status-select" onchange="this.form.submit()">
                    <?php foreach (['pending', 'preparing', 'ready', 'completed', 'cancelled'] as $st): ?>
                      <option value="<?php echo $st; ?>" <?php echo $ord['status'] === $st ? 'selected' : ''; ?>>
                        <?php echo ucfirst($st); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </div>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 13px; color: #555; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
              <div><strong>Type:</strong> <?php echo htmlspecialchars($ord['order_type']); ?></div>
              <div><strong>Payment:</strong> <?php echo htmlspecialchars($ord['payment_method']); ?></div>
              <div><strong>Prep Time:</strong> <?php echo htmlspecialchars($ord['prep_time'] ?? 'N/A'); ?></div>
              <div><strong>Placed At:</strong> <?php echo date('M j, Y - g:i A', strtotime($ord['created_at'])); ?></div>
            </div>

            <table>
              <thead>
                <tr>
                  <th>Item</th>
                  <th class="right">Price</th>
                  <th class="right">Qty</th>
                  <th class="right">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($ord['items'])): ?>
                  <?php foreach ($ord['items'] as $item): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                      <td class="right">₱<?php echo number_format($item['price'], 2); ?></td>
                      <td class="right"><?php echo (int)$item['quantity']; ?></td>
                      <td class="right">₱<?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" style="text-align: center; color: #888;">No item breakdown recorded</td></tr>
                <?php endif; ?>
              </tbody>
              <tfoot>
                <tr>
                  <th colspan="3" class="right">Total Quantity:</th>
                  <th class="right"><?php echo (int)$ord['total_qty']; ?></th>
                </tr>
                <tr>
                  <th colspan="3" class="right" style="font-size: 15px;">Grand Total:</th>
                  <th class="right" style="font-size: 15px; color: #c62828;">₱<?php echo number_format($ord['grand_total'], 2); ?></th>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <footer>
    SL Eats &bull; SIBONGA COMMUNITY COLLEGE (SCC) &bull; A project by BSIT 2I &bull; &copy; <?php echo date('Y'); ?>
  </footer>
</body>
</html>

