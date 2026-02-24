<?php
session_start();
require_once "./Database/dbconnect.php"; // Your DB
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account - MotoRide</title>
  <link rel="stylesheet" href="./Assets/Styles/signup.css">
  <style>
    .alert {
      padding: 12px;
      margin-bottom: 10px;
      border-radius: 5px;
      font-size: 14px;
    }

    .alert.error {
      background: #ffdddd;
      color: #b30000;
    }

    .alert.success {
      background: #ddffdd;
      color: #006600;
    }
  </style>
</head>

<body>

  <div class="container">

    <!-- LEFT SIDE ORANGE PANEL -->
    <div class="left-panel">
      <div class="back-home" onclick="window.location.href='login.php'">
        <span>&larr;</span> Back to Home
      </div>

      <div class="logo-text">
        <div class="logo">
          <img src="./Assets/Svg/motorcycle-svgrepo-com.svg" alt="Logo">
        </div>
        <h2>MotoRide</h2>
      </div>

      <h3>Join MotoRide Today</h3>
      <p>Create your account and start your motorcycle adventure with exclusive benefits.</p>

      <ul class="features">
        <li><span class="check">✔</span> <b>Access Premium Fleet</b><br>Choose from 50+ motorcycles</li>
        <li><span class="check">✔</span> <b>Instant Booking</b><br>Reserve and ride in minutes</li>
        <li><span class="check">✔</span> <b>Member Rewards</b><br>Earn points and exclusive discounts</li>
        <li><span class="check">✔</span> <b>24/7 Support</b><br>We're here whenever you need us</li>
      </ul>

      <footer>© 2025 MotoRide. All rights reserved.</footer>
    </div>

    <!-- RIGHT SIDE SIGNUP FORM -->
    <div class="right-panel">
      <div class="signup-card">

        <h2>Create Your Account</h2>
        <p class="sub">Start your motorcycle rental journey with MotoRide</p>

        <form action="./Authentication/signup-handler.php" method="POST">
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


          <label>Full Name</label>
          <div class="input-box">
            <input type="text" name="name" placeholder="John Doe" required>
          </div>

          <label>Email Address</label>
          <div class="input-box">
            <input type="email" name="email" placeholder="you@example.com" required>
          </div>

          <label>Phone Number</label>
          <div class="input-box">
            <input type="text" name="phone" placeholder="+63 912 345 6789" required>
          </div>

          <label>Address</label>
          <div class="input-box">
            <input type="text" name="address" placeholder="Barangay • Street • City" required>
          </div>

          <label>Password</label>
          <div class="input-box">
            <input type="password" name="password" placeholder="••••••••" required>
          </div>

          <label>Confirm Password</label>
          <div class="input-box">
            <input type="password" name="confirm_password" placeholder="••••••••" required>
          </div>

          <label class="agree">
            <input type="checkbox" required> I agree to the
            <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
          </label>

          <button type="submit" class="signup-btn">Create Account</button>

        </form>

        <p class="login-link">
          Already have an account? <a href="login.php">Sign in</a>
        </p>

      </div>
    </div>

  </div>

</body>

</html>