<?php
// SL Eats - Login Portal (Student & Admin Authentication)
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/db.php';

$active_role = isset($_GET['role']) && $_GET['role'] === 'admin' ? 'admin' : 'student';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
$msg = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'logged_out') {
  $msg = 'You have been successfully logged out.';
}

// If student is already logged in and views student portal, go to menu
if ($active_role === 'student' && !empty($_SESSION['student_logged_in'])) {
  header('Location: index.php');
  exit;
}

// If admin is already logged in and views admin portal, go to admin panel
if ($active_role === 'admin' && !empty($_SESSION['admin_logged_in'])) {
  header('Location: admin.php');
  exit;
}

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = isset($_POST['action']) ? trim($_POST['action']) : '';

  // 1. STUDENT LOGIN
  if ($action === 'student_login') {
    $name = isset($_POST['student_name']) ? trim($_POST['student_name']) : '';
    $student_id = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';

    if ($name === '' || $student_id === '') {
      header('Location: login.php?role=student&error=' . urlencode('Please enter both your Full Name and Student/Teacher ID.'));
      exit;
    }

    // Save or update student in database log
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
      $stmt = $conn->prepare("INSERT INTO `students` (`student_name`, `student_id`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `student_name` = VALUES(`student_name`), `last_login` = NOW()");
      if ($stmt) {
        $stmt->bind_param("ss", $name, $student_id);
        $stmt->execute();
        $stmt->close();
      }
    }

    // Set student session
    $_SESSION['student_logged_in'] = true;
    $_SESSION['student_name'] = $name;
    $_SESSION['student_id'] = $student_id;

    header('Location: index.php');
    exit;
  }

  // 2. ADMIN LOGIN
  if ($action === 'admin_login') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if ($username === '' || $password === '') {
      header('Location: login.php?role=admin&error=' . urlencode('Please enter both Admin Username and Password.'));
      exit;
    }

    $admin_authenticated = false;
    $admin_fullname = 'SCC Administrator';

    // Verify against MySQL admins table
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
      $stmt = $conn->prepare("SELECT * FROM `admins` WHERE `username` = ?");
      if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
          // Check hashed password or plain match fallback
          if (password_verify($password, $row['password']) || $password === $row['password']) {
            $admin_authenticated = true;
            $admin_fullname = $row['full_name'] ?: 'Administrator';
          }
        }
        $stmt->close();
      }
    }

    // Fallback verification for sample admin credentials
    if (!$admin_authenticated && $username === 'admin' && $password === 'admin123') {
      $admin_authenticated = true;
    }

    if ($admin_authenticated) {
      $_SESSION['admin_logged_in'] = true;
      $_SESSION['admin_username'] = $username;
      $_SESSION['admin_name'] = $admin_fullname;

      header('Location: admin.php');
      exit;
    } else {
      header('Location: login.php?role=admin&error=' . urlencode('Invalid admin username or password. Check sample credentials.'));
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - SCC College SL Eats Canteen</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header>
    <div class="scc-badge">SIBONGA COMMUNITY COLLEGE (SCC)</div>
    <h1> 🍚SL Eats🥤</h1>
    <p>Campus Foods Online Ordering &bull; SCC Canteen Food Service Project</p>
  </header>

  <div class="container">
    <div class="login-container">
      <?php if ($msg): ?>
        <div class="success"><?php echo $msg; ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
      <?php endif; ?>

      <div class="login-tabs">
        <a href="login.php?role=student" class="login-tab <?php echo $active_role === 'student' ? 'active' : ''; ?>">
          <button>🎓 Student Portal</button>
        </a>
        <a href="login.php?role=admin" class="login-tab <?php echo $active_role === 'admin' ? 'active' : ''; ?>">
          <button>🛡️ Admin only Portal</button>
        </a>
      </div>

      <?php if ($active_role === 'student'): ?>
        <!-- Student Login Form -->
        <div class="card">
          <h2>🎓 Student / Teacher Login</h2>
          <p style="font-size: 13px; color: #666; margin-bottom: 16px;">
            Enter your name and school ID to start ordering food from the SCC canteen.
          </p>

          <form action="login.php" method="post">
            <input type="hidden" name="action" value="student_login">

            <label for="student_name">Full Name & Section *</label>
            <input type="text" id="student_name" name="student_name" 
                   placeholder="e.g. Kent Cervantes BSIT-2I" required autofocus>

            <label for="student_id">Student / Teacher ID *</label>
            <input type="text" id="student_id" name="student_id" 
                   placeholder="e.g. 2024-00123" required>

            <button type="submit" class="btn" style="width: 100%; margin-top: 6px;">
              Enter Canteen Menu →
            </button>
          </form>

          <div style="font-size: 12px; color: #777; margin-top: 15px; text-align: center;">
            🔒 No password required for students &bull; Just enter your school ID & name.
          </div>
        </div>

      <?php else: ?>
        <!-- Admin Login Form -->
        <div class="card">
          <h2>🛡️ Administrator Login </h2>
          <p style="font-size: 13px; color: #666; margin-bottom: 16px;">
            Manage menu products, monitor live orders, and update canteen food status.
          </p>

          <form action="login.php" method="post">
            <input type="hidden" name="action" value="admin_login">

            <label for="username">Admin Username *</label>
            <input type="text" id="username" name="username" 
                   placeholder="e.g. admin" required autofocus>

            <label for="password">Password *</label>
            <input type="password" id="password" name="password" 
                   placeholder="••••••••" required 
                   style="width: 100%; padding: 8px; border: 1px solid #bbb; border-radius: 4px; margin-bottom: 12px; font-size: 14px;">

            <button type="submit" class="btn" style="width: 100%; margin-top: 6px;">
              Sign In to Admin Panel →
            </button>
          </form>

          <!-- Sample Admin Credentials for Professor / Testing -->
          <div class="sample-creds">
            <strong>💡 Sample Admin Credentials:</strong><br>
            Username: <code>admin</code><br>
            Password: <code>admin123</code>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <footer>
    SL Eats &bull; SIBONGA COMMUNITY COLLEGE (SCC) &bull; A project by BSIT 2I &bull; &copy; <?php echo date('Y'); ?>
  </footer>
</body>
</html>

