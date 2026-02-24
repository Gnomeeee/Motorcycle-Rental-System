<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
  header("Location: ../login.php");
  exit();
}

require_once "../Database/dbconnect.php";

// Fetch admin info
$admin_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT full_name, role, profile_image FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$admin_name = $admin['full_name'] ?? 'Admin User';
$admin_role = $admin['role'] ?? 'Administrator';
$profile_img = $admin['profile_image'] ?? "https://ui-avatars.com/api/?name=Admin+User&background=random";

// Validate reservation ID
if (!isset($_GET['id'])) {
  die("Invalid reservation ID.");
}

$reservation_id = intval($_GET['id']);

// Fetch reservation details
$sql = "
    SELECT r.*, 
           u.full_name AS customer_name, u.email, u.contact_number,
           m.model AS motorcycle_model, m.plate_number, m.rate_per_hour
    FROM reservations r
    JOIN users u ON r.user_id = u.user_id
    JOIN motorcycles m ON r.motorcycle_id = m.motorcycle_id
    WHERE r.reservation_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$reservation = $stmt->get_result()->fetch_assoc();

if (!$reservation) {
  die("Reservation not found.");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Reservation Details — MotoRide Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    body {
      font-family: 'Inter', sans-serif;
      margin: 0;
      background: #f3f4f6;
      color: #111827;
    }

    /* TOPBAR */
    .topbar {
      background: #111827;
      color: white;
      padding: 0 24px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .brand-icon {
      background: #ff6a00;
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
    }

    .brand-text h1 {
      margin: 0;
      font-size: 16px;
      font-weight: 600;
    }

    .brand-text span {
      font-size: 11px;
      color: #9ca3af;
    }

    .profile img {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      border: 2px solid #374151;
    }

    /* PAGE CONTAINER */
    .container {
      max-width: 960px;
      margin: 30px auto;
      background: white;
      padding: 35px;
      border-radius: 14px;
      border: 1px solid #e5e7eb;
    }

    .section-title {
      font-size: 13px;
      font-weight: 600;
      color: #6b7280;
      text-transform: uppercase;
      margin-bottom: 10px;
    }

    .detail-box {
      background: #f9fafb;
      padding: 18px;
      border-radius: 12px;
      border: 1px solid #e5e7eb;
      margin-bottom: 24px;
    }

    .row {
      font-size: 15px;
      margin-bottom: 10px;
    }

    .label {
      font-weight: 600;
      color: #374151;
    }

    /* STATUS TAG */
    .status-tag {
      padding: 6px 14px;
      border-radius: 16px;
      font-size: 13px;
      font-weight: 600;
    }

    .Pending {
      background: #fef3c7;
      color: #b45309;
    }

    .Approved {
      background: #d1fae5;
      color: #065f46;
    }

    .Rejected {
      background: #fee2e2;
      color: #991b1b;
    }

    .Completed {
      background: #dbeafe;
      color: green;
    }

    .Cancelled {
      background: #fee2e2;
      color: #991b1b;
    }

    /* BACK BUTTON */
    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #e5e7eb;
      color: #111827;
      padding: 10px 18px;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 500;
      margin-bottom: 25px;
    }

    .btn-back:hover {
      background: #d1d5db;
    }

    /* ACTION BUTTONS */
    .action-buttons button {
      padding: 10px 18px;
      border-radius: 10px;
      border: none;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      margin-right: 10px;
    }

    .approve-btn {
      background: #16a34a;
      color: white;
    }

    .reject-btn {
      background: #ef4444;
      color: white;
    }

    .complete-btn {
      background: #2563eb;
      color: white;
    }
  </style>

</head>

<body>

  <header class="topbar">
    <div class="brand">
      <div class="brand-icon"><i class="fa-solid fa-motorcycle"></i></div>
      <div class="brand-text">
        <h1>MotoRide Admin</h1>
        <span>Management Portal</span>
      </div>
    </div>
    <div class="profile">
      <img src="<?= htmlspecialchars($profile_img) ?>">
    </div>
  </header>

  <div class="container">

    <a href="reservations.php" class="btn-back">
      <i class="fa-solid fa-arrow-left"></i> Back to Reservations
    </a>

    <h2 style="margin-top:10px;">Reservation Details</h2>

    <!-- CUSTOMER -->
    <p class="section-title">Customer Information</p>
    <div class="detail-box">
      <div class="row"><span class="label">Name:</span> <?= htmlspecialchars($reservation['customer_name']) ?></div>
      <div class="row"><span class="label">Email:</span> <?= htmlspecialchars($reservation['email']) ?></div>
      <div class="row"><span class="label">Contact:</span> <?= htmlspecialchars($reservation['contact_number']) ?></div>
    </div>

    <!-- MOTORCYCLE -->
    <p class="section-title">Motorcycle Information</p>
    <div class="detail-box">
      <div class="row"><span class="label">Model:</span> <?= htmlspecialchars($reservation['motorcycle_model']) ?></div>
      <div class="row"><span class="label">Plate #:</span> <?= htmlspecialchars($reservation['plate_number']) ?></div>
      <div class="row"><span class="label">Rate / Hour:</span> ₱<?= number_format($reservation['rate_per_hour'], 2) ?></div>
    </div>

    <!-- RESERVATION -->
    <p class="section-title">Reservation Details</p>
    <div class="detail-box">
      <div class="row"><span class="label">Start:</span> <?= $reservation['start_date'] ?></div>
      <div class="row"><span class="label">End:</span> <?= $reservation['end_date'] ?></div>
      <div class="row"><span class="label">Total Cost:</span> ₱<?= number_format($reservation['total_cost'], 2) ?></div>
      <div class="row">
        <span class="label">Status:</span>
        <span class="status-tag <?= $reservation['status'] ?>"><?= $reservation['status'] ?></span>
      </div>
    </div>

    <!-- ACTION BUTTONS -->
    <?php if ($reservation['status'] === 'Pending'): ?>
      <div class="action-buttons">
        <form method="post" action="update_reservation.php">
          <input type="hidden" name="reservation_id" value="<?= $reservation['reservation_id'] ?>">
          <button name="action" value="approve" class="approve-btn">Approve</button>
          <button name="action" value="reject" class="reject-btn">Reject</button>
        </form>
      </div>

    <?php elseif ($reservation['status'] === 'Approved'): ?>
      <div class="action-buttons">
        <form method="post" action="update_reservation.php">
          <input type="hidden" name="reservation_id" value="<?= $reservation['reservation_id'] ?>">
          <button name="action" value="completed" class="complete-btn">Mark as Completed</button>
        </form>
      </div>
    <?php endif; ?>

  </div>

</body>

</html>