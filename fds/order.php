<?php
// Campus Kainan - Order Processing & Receipt
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/db.php';

// Fallback menu in case session is empty
$default_menu = [
  'pastil'        => ['name' => 'Chicken pastil w/ Rice',     'price' => 20, 'type' => 'meal'],
  'siomai'        => ['name' => 'Beff siomai  w/ Rice',       'price' => 25, 'type' => 'meal'],
  'Fried Chicken' => ['name' => 'Fried Chicken',              'price' => 45, 'type' => 'meal'],
  'pancit'        => ['name' => 'Pancit Canton',             'price' => 35, 'type' => 'meal'],
  'lumpia'        => ['name' => 'Lumpiang Shanghai w/ Rice', 'price' => 45, 'type' => 'meal'],
  'buko'          => ['name' => 'Buko Juice',                'price' => 25, 'type' => 'drink'],
  'icedtea'       => ['name' => 'Iced Tea',                  'price' => 25, 'type' => 'drink'],
  'Coke'          => ['name' => 'Coke (in can)',             'price' => 25, 'type' => 'drink'],
];

if (isset($_SESSION['menu']) && is_array($_SESSION['menu'])) {
  $menu = $_SESSION['menu'];
} else {
  $menu = [];
  $res = $conn->query("SELECT * FROM `menu_items` WHERE `is_available` = 1");
  if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
      $menu[$row['item_code']] = [
        'name'  => $row['name'],
        'price' => (float)$row['price'],
        'type'  => $row['type'],
      ];
    }
  }
  if (empty($menu)) {
    $menu = $default_menu;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name       = isset($_POST['name']) ? trim($_POST['name']) : '';
  $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
  $order_type = isset($_POST['order_type']) && $_POST['order_type'] === 'take_out' ? 'take_out' : 'dine_in';
  $payment    = isset($_POST['payment']) && $_POST['payment'] === 'gcash' ? 'gcash' : 'cash';
  $quantities = isset($_POST['qty']) && is_array($_POST['qty']) ? $_POST['qty'] : [];

  // Validation
  if ($name === '' || $student_id === '') {
    header('Location: index.php?error=' . urlencode('Please fill in your Name and Student/Teacher ID.'));
    exit;
  }

  $order_items = [];
  $total_qty = 0;
  $grand_total = 0;

  foreach ($quantities as $item_key => $qty) {
    $qty = (int)$qty;
    if ($qty > 0 && isset($menu[$item_key])) {
      $item = $menu[$item_key];
      $item_price = (float)$item['price'];
      $subtotal = $item_price * $qty;

      $order_items[] = [
        'key'      => $item_key,
        'name'     => $item['name'],
        'price'    => $item_price,
        'qty'      => $qty,
        'subtotal' => $subtotal,
      ];

      $total_qty += $qty;
      $grand_total += $subtotal;
    }
  }

  if ($total_qty <= 0) {
    header('Location: index.php?error=' . urlencode('Please select at least 1 item to place an order.'));
    exit;
  }

  if ($total_qty <= 2) {
    $prep_time = '10 - 15 mins';
  } elseif ($total_qty <= 5) {
    $prep_time = '15 - 20 mins';
  } else {
    $prep_time = '20 - 30 mins';
  }

  $order_id = 'SLE-' . date('md') . '-' . str_pad((string)rand(100, 999), 3, '0', STR_PAD_LEFT);

  $order = [
    'order_id'    => $order_id,
    'name'        => $name,
    'student_id'  => $student_id,
    'order_type'  => $order_type === 'take_out' ? 'Take Out' : 'Dine In',
    'payment'     => $payment === 'gcash' ? 'GCash' : 'Cash at counter',
    'order_date'  => date('F j, Y, g:i A'),
    'prep_time'   => $prep_time,
    'items'       => $order_items,
    'total_qty'   => $total_qty,
    'grand_total' => $grand_total,
    'created_at'  => date('Y-m-d H:i:s'),
  ];

  $_SESSION['last_order'] = $order;

  // Save to MySQL database
  if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $stmt = $conn->prepare("INSERT INTO `orders` (`order_id`, `customer_name`, `student_id`, `order_type`, `payment_method`, `total_qty`, `grand_total`, `prep_time`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    if ($stmt) {
      $stmt->bind_param(
        "sssssids",
        $order['order_id'],
        $order['name'],
        $order['student_id'],
        $order['order_type'],
        $order['payment'],
        $order['total_qty'],
        $order['grand_total'],
        $order['prep_time']
      );
      $stmt->execute();
      $stmt->close();
    }

    $item_stmt = $conn->prepare("INSERT INTO `order_items` (`order_id`, `item_code`, `item_name`, `price`, `quantity`, `subtotal`) VALUES (?, ?, ?, ?, ?, ?)");
    if ($item_stmt) {
      foreach ($order['items'] as $it) {
        $item_stmt->bind_param(
          "sssdid",
          $order['order_id'],
          $it['key'],
          $it['name'],
          $it['price'],
          $it['qty'],
          $it['subtotal']
        );
        $item_stmt->execute();
      }
      $item_stmt->close();
    }
  }

  // Redirect using PRG pattern to prevent form resubmission on refresh
  header('Location: order.php?order_id=' . urlencode($order_id));
  exit;
}

// Display Order Receipt (GET request)
if (!isset($_SESSION['last_order']) || !is_array($_SESSION['last_order'])) {
  header('Location: index.php');
  exit;
}

$order = $_SESSION['last_order'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Receipt #<?php echo htmlspecialchars($order['order_id']); ?> - SL Eats SCC</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header>
    <div class="scc-badge"> SIBONGA COMMUNITY COLLEGE</div>
    <h1> 🍚SL Eats🥤</h1>
    <p>Campus Foods Online Ordering &bull; SCC Canteen Food Service</p>
  </header>

  <div class="container">
    <?php if (!empty($_SESSION['student_name'])): ?>
      <div class="user-bar">
        <div class="user-info">
          🎓 Student: <strong><?php echo htmlspecialchars($_SESSION['student_name']); ?></strong> 
          (ID: <?php echo htmlspecialchars($_SESSION['student_id'] ?? ''); ?>)
        </div>
        <div>
          <a href="logout.php?role=student" class="logout-btn"><button>🚪 Logout</button></a>
        </div>
      </div>
    <?php endif; ?>

    <div class="success">
      🎉 <strong>Order Placed Successfully!</strong> Your order <strong>#<?php echo htmlspecialchars($order['order_id']); ?></strong> has been received.
    </div>

    <div class="card">
      <h2>Order Receipt (#<?php echo htmlspecialchars($order['order_id']); ?>)</h2>

      <table style="margin-bottom: 20px;">
        <tr>
          <th style="width: 35%;">Order ID</th>
          <td><strong>#<?php echo htmlspecialchars($order['order_id']); ?></strong></td>
        </tr>
        <tr>
          <th>Customer Name & Set</th>
          <td><?php echo htmlspecialchars($order['name']); ?></td>
        </tr>
        <tr>
          <th>Student/Teacher ID</th>
          <td><?php echo htmlspecialchars($order['student_id']); ?></td>
        </tr>
        <tr>
          <th>Order Type</th>
          <td>
            <?php echo $order['order_type'] === 'Take Out' ? '🥡 Take Out' : '🍽️ Dine In'; ?>
          </td>
        </tr>
        <tr>
          <th>Payment Method</th>
          <td>
            <?php echo $order['payment'] === 'GCash' ? '📱 GCash (show reference at counter)' : '💵 Cash at counter'; ?>
          </td>
        </tr>
        <tr>
          <th>Date & Time</th>
          <td><?php echo htmlspecialchars($order['order_date']); ?></td>
        </tr>
      </table>

      <h2>Items Ordered</h2>
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
          <?php foreach ($order['items'] as $item): ?>
            <tr>
              <td><?php echo htmlspecialchars($item['name']); ?></td>
              <td class="right">₱<?php echo number_format($item['price'], 2); ?></td>
              <td class="right"><?php echo (int)$item['qty']; ?></td>
              <td class="right">₱<?php echo number_format($item['subtotal'], 2); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="3" class="right">Total Quantity:</th>
            <th class="right"><?php echo (int)$order['total_qty']; ?></th>
          </tr>
          <tr>
            <th colspan="3" class="right" style="font-size: 16px;">Grand Total:</th>
            <th class="right" style="font-size: 16px; color: #c62828;">₱<?php echo number_format($order['grand_total'], 2); ?></th>
          </tr>
        </tfoot>
      </table>

      <div class="notice">
        Estimated Preparation Time:
        <div class="time"><?php echo htmlspecialchars($order['prep_time']); ?></div>
      </div>

      <div class="ready">
        <?php if ($order['payment'] === 'GCash'): ?>
          📱 Please prepare your GCash payment reference to present at the counter!
        <?php else: ?>
          💵 Please proceed to the counter with exact cash to pay and claim your food!
        <?php endif; ?>
      </div>

      <div class="actions" style="display: flex; gap: 12px; margin-top: 18px; flex-wrap: wrap;">
        <a href="index.php" class="btn">Place Another Order</a>
        <button type="button" class="btn" onclick="window.print()" style="background: #2e7d32;">🖨️ Print Receipt</button>
      </div>
    </div>
  </div>

  <footer>
    SL Eats &bull; SIBONGA COMMUNITY COLLEGE (SCC) &bull; A project by BSIT 2I &bull; &copy; <?php echo date('Y'); ?>
  </footer>
</body>
</html>
