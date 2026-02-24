<?php
session_start();
require_once "./Database/dbconnect.php"; // Your DB
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MotoRide Login</title>
  <link rel="stylesheet" href="./Assets/Styles/cus-login.css">
</head>

<body>

  <div class="container">

    <!-- LEFT SIDE BACKGROUND PANEL -->
    <div class="left-panel">
      <div class="back-home" onclick="window.location.href='index.php'">
        <span>&larr;</span> Back to Home
      </div>

      <div class="logo-text">
        <div class="logo">
          <img src="./Assets/Svg/motorcycle-svgrepo-com.svg" alt="Logo">
        </div>
        <h2>MotoRide</h2>
      </div>

      <h3>Welcome Back to MotoRide</h3>
      <p>Sign in to access your account and continue your motorcycle adventure.</p>

      <ul class="features">
        <li><span class="dot"></span> <b>Quick & Easy Booking</b><br>Reserve your motorcycle in minutes</li>
        <li><span class="dot"></span> <b>Track Your Rentals</b><br>Manage your bookings in one place</li>
        <li><span class="dot"></span> <b>Exclusive Deals</b><br>Get special offers for members</li>
      </ul>

      <footer>© 2025 MotoRide. All rights reserved.</footer>
    </div>

    <!-- RIGHT SIDE LOGIN CARD -->
    <div class="right-panel">
      <div class="login-card">

        <h2>Login</h2>
        <p class="sub">Sign in to browse and rent motorcycles</p>

        <form action="./Authentication/login-handler.php" method="POST">
          <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error">
              <?= $_SESSION['error'];
              unset($_SESSION['error']); ?>
            </div>
          <?php endif; ?>

          <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success">
              <?= $_SESSION['success'];
              unset($_SESSION['success']); ?>
            </div>
          <?php endif; ?>

          <label>Email Address</label>
          <div class="input-box">
            <input type="email" name="email" placeholder="you@example.com" required>
          </div>

          <label>Password</label>
          <div class="input-box">
            <input type="password" name="password" placeholder="••••••••" required>
          </div>

          <div class="links">
            <label class="remember">
              <input type="checkbox" name="remember"> Remember me
            </label>
            <label class="forgot">
              <a href="">Forgot password?</a>
            </label>
          </div>

          <button type="submit" class="login-btn">Sign In</button>

          <div class="sign-up-link">
            <label for="link">Don't have an account? <a href="signup.php">Sign in</a></label>
          </div>

        </form>

      </div>
    </div>

  </div>

</body>

</html>