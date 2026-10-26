<?php
// Campus Kainan - simple canteen ordering page
// Made for the Student Council food service project
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Require student login
if (empty($_SESSION['student_logged_in'])) {
  header('Location: login.php?role=student');
  exit;
}

require_once __DIR__ . '/db.php';

// Fetch menu items from MySQL database
$menu = [];
$result = $conn->query("SELECT * FROM `menu_items` WHERE `is_available` = 1 ORDER BY FIELD(`type`, 'meal', 'drink'), `id` ASC");
if ($result && $result->num_rows > 0) {
  while ($row = $result->fetch_assoc()) {
    $menu[$row['item_code']] = [
      'name'  => $row['name'],
      'price' => (float)$row['price'],
      'type'  => $row['type'],
    ];
  }
}

// Fallback in case of database connectivity issues
if (empty($menu)) {
  $menu = [
    'pastil'        => ['name' => 'Chicken pastil w/ Rice',     'price' => 20, 'type' => 'meal'],
    'siomai'        => ['name' => 'Beff siomai  w/ Rice',       'price' => 25, 'type' => 'meal'],
    'Fried Chicken' => ['name' => 'Fried Chicken',              'price' => 45, 'type' => 'meal'],
    'pancit'        => ['name' => 'Pancit Canton',             'price' => 35, 'type' => 'meal'],
    'lumpia'        => ['name' => 'Lumpiang Shanghai w/ Rice', 'price' => 45, 'type' => 'meal'],
    'buko'          => ['name' => 'Buko Juice',                'price' => 25, 'type' => 'drink'],
    'icedtea'       => ['name' => 'Iced Tea',                  'price' => 25, 'type' => 'drink'],
    'Coke'          => ['name' => 'Coke (in can)',             'price' => 25, 'type' => 'drink'],
  ];
}
$_SESSION['menu'] = $menu;

$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SL Eats - SIBONGA COMMUNITY COLLEGE (SCC)</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header>
    <div class="scc-badge">SIBONGA COMMUNITY COLLEGE (SCC)</div>
    <h1> 🍚SL Eats🥤</h1>
    <p>Campus Foods Online Ordering &bull; SCC Canteen Food Service</p>
  </header>

  <div class="container">
    <!-- Student Session Bar -->
    <div class="user-bar">
      <div class="user-info">
        🎓 Logged in as: <strong><?php echo htmlspecialchars($_SESSION['student_name']); ?></strong> 
        <span style="color: #666; font-size: 13px;">(ID: <?php echo htmlspecialchars($_SESSION['student_id']); ?>)</span>
      </div>
      <div style="display: flex; gap: 8px; align-items: center;">
        <a href="logout.php?role=student" class="logout-btn"><button>🚪 Logout</button></a>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="order.php" method="post">
      <div class="card">
        <h2>Menu</h2>
        <?php foreach (['meal' => '🍚 Meals', 'drink' => '🥤 Drinks'] as $type => $label): ?>
          <h3 class="section"><?php echo $label; ?></h3>
          <div class="menu">
            <?php foreach ($menu as $key => $item): if ($item['type'] !== $type) continue; ?>
              <div class="item">
                <div>
                  <div class="name"><?php echo $item['name']; ?></div>
                  <div class="price">₱<?php echo number_format($item['price'], 2); ?></div>
                </div>
                <input type="number" name="qty[<?php echo $key; ?>]" min="0" max="10" value="0">
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="card">
        <h2>Your Details</h2>
        <label for="name">Name & Set</label>
        <input type="text" id="name" name="name" 
               value="<?php echo htmlspecialchars($_SESSION['student_name'] ?? ''); ?>" 
               placeholder="e.g. Chad Redoblado BSIT 2-I" required>

        <label for="student_id">Student/Teacher ID</label>
        <input type="text" id="student_id" name="student_id" 
               value="<?php echo htmlspecialchars($_SESSION['student_id'] ?? ''); ?>" 
               placeholder="e.g. 1234567" required>

        <label>Order Type</label>
        <div class="radio-group">
          <label><input type="radio" name="order_type" value="dine_in" checked> 🍽️ Dine In</label>
          <label><input type="radio" name="order_type" value="take_out"> 🥡 Take Out</label>
        </div>

        <label for="payment">Payment</label>
        <select id="payment" name="payment">
          <option value="cash">Cash at counter</option>
          <option value="gcash">GCash (show reference at counter)</option>
        </select>

        <div class="total-box">
          <span>Total Amount:</span>
          <span class="amount">₱<span id="liveTotal">0.00</span></span>
        </div>

        <button type="submit">Place Order</button>
      </div>
    </form>
  </div>

  <footer>
    SL Eats &bull; SIBONGA COMMUNITY COLLEGE (SCC) &bull; A project by BSIT 2I &bull; &copy; <?php echo date('Y'); ?>
  </footer>

  <script>
    const form = document.querySelector('form');
    const qtyInputs = document.querySelectorAll('input[type="number"]');
    const liveTotalEl = document.getElementById('liveTotal');

    const prices = <?php
      $prices = [];
      foreach ($menu as $k => $item) {
        $prices[$k] = $item['price'];
      }
      echo json_encode($prices);
    ?>;

    function calculateTotal() {
      let total = 0;
      let totalItems = 0;
      qtyInputs.forEach(input => {
        const match = input.name.match(/qty\[(.*?)\]/);
        if (match) {
          const itemKey = match[1];
          const qty = parseInt(input.value) || 0;
          if (qty > 0 && prices[itemKey] !== undefined) {
            total += qty * prices[itemKey];
            totalItems += qty;
          }
        }
      });
      if (liveTotalEl) {
        liveTotalEl.textContent = total.toFixed(2);
      }
      return { total, totalItems };
    }

    qtyInputs.forEach(input => {
      input.addEventListener('input', calculateTotal);
      input.addEventListener('change', calculateTotal);
    });

    form.addEventListener('submit', function (e) {
      const { totalItems } = calculateTotal();
      if (totalItems <= 0) {
        e.preventDefault();
        alert('Please select at least 1 item to place an order.');
      }
    });

    calculateTotal();
  </script>
</body>
</html>

</html>