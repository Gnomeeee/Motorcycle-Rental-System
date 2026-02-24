<?php
session_start();
require_once "../Database/dbconnect.php";

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: ../login.php");
  exit();
}

$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$remember = isset($_POST['remember']);

// Validate
if (!$email || !$password) {
  $_SESSION['error'] = "Email and password are required.";
  header("Location: ../login.php");
  exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $_SESSION['error'] = "Invalid email format.";
  header("Location: ../login.php");
  exit();
}

// Fetch user
$stmt = $conn->prepare("SELECT user_id, full_name, email, password, role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  $_SESSION['error'] = "Account not found.";
  header("Location: ../login.php");
  exit();
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user['password'])) {
  $_SESSION['error'] = "Incorrect password.";
  header("Location: ../login.php");
  exit();
}

// Store session
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['role'] = $user['role'];

// Remember me
if ($remember) {
  setcookie("remember_email", $email, time() + 86400 * 30, "/");
} else {
  setcookie("remember_email", "", time() - 3600, "/");
}

// Redirect based on role
switch (strtolower($user['role'])) {
  case 'admin':
    header("Location: ../Admin/dashboard.php");
    break;
  case 'customer':
    header("Location: ../Customer/dashboard.php");
    break;
  default:
    header("Location: ../login.php");
    break;
}
exit();
