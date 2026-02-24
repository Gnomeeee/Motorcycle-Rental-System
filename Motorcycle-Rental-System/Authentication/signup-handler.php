<?php
session_start();
require_once "../Database/dbconnect.php"; // DB Connection

// Allow only POST request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  $_SESSION['error'] = "Invalid request method.";
  header("Location: ../signup.php");
  exit();
}

// Collect user inputs
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$password = trim($_POST['password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');

$role = "customer";

// ADI PAN ERROR HANDLING AND VALIDATION

if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($password) || empty($confirm_password)) {
  $_SESSION['error'] = "Please fill out all required fields.";
  header("Location: ../signup.php");
  exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $_SESSION['error'] = "Invalid email format.";
  header("Location: ../signup.php");
  exit();
}

// Phone number validation
if (!preg_match("/^[0-9+\s-]{10,15}$/", $phone)) {
  $_SESSION['error'] = "Invalid phone number format.";
  header("Location: ../signup.php");
  exit();
}

// Address validation
if (strlen($address) < 5) {
  $_SESSION['error'] = "Please enter a valid address.";
  header("Location: ../signup.php");
  exit();
}
// password validation
if (strlen($password) < 6) {
  $_SESSION['error'] = "Password must be at least 6 characters.";
  header("Location: ../signup.php");
  exit();
}
// password validation

if ($password !== $confirm_password) {
  $_SESSION['error'] = "Passwords do not match.";
  header("Location: ../signup.php");
  exit();
}

// QUERY FOR CHECKING EMAIL IN THE DATABASE IF ALREADY EXISTS
$checkEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$checkEmail->bind_param("s", $email);
$checkEmail->execute();
$emailResult = $checkEmail->get_result();

if ($emailResult->num_rows > 0) {
  $_SESSION['error'] = "Email is already registered.";
  header("Location: ../signup.php");
  exit();
}

// CHECK IF PHONE EXISTS 
$checkPhone = $conn->prepare("SELECT user_id FROM users WHERE contact_number = ? LIMIT 1");
$checkPhone->bind_param("s", $phone);
$checkPhone->execute();
$phoneResult = $checkPhone->get_result();

if ($phoneResult->num_rows > 0) {
  $_SESSION['error'] = "Phone number is already in use.";
  header("Location: ../signup.php");
  exit();
}

// HASH PASSWORD 
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// INSERT USER IN THE DATABASE 

$stmt = $conn->prepare("
    INSERT INTO users (full_name, email, password, contact_number, role, address, date_created)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("ssssss", $name, $email, $hashed_password, $phone, $role, $address);

if ($stmt->execute()) {
  $_SESSION['success'] = "Account created successfully! You may now log in.";
  header("Location: ../login.php");
  exit();
} else {
  $_SESSION['error'] = "An unexpected error occurred. Please try again.";
  header("Location: ../signup.php");
  exit();
}
