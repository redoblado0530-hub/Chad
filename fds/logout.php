<?php
// SL Eats - Logout Handler
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$role = isset($_GET['role']) ? trim($_GET['role']) : '';

if ($role === 'admin') {
  unset($_SESSION['admin_logged_in']);
  unset($_SESSION['admin_username']);
  unset($_SESSION['admin_name']);
  header('Location: login.php?role=admin&msg=logged_out');
  exit;
} elseif ($role === 'student') {
  unset($_SESSION['student_logged_in']);
  unset($_SESSION['student_name']);
  unset($_SESSION['student_id']);
  header('Location: login.php?role=student&msg=logged_out');
  exit;
} else {
  // Clear entire session
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params["path"], $params["domain"],
      $params["secure"], $params["httponly"]
    );
  }
  session_destroy();
  header('Location: login.php?msg=logged_out');
  exit;
}

