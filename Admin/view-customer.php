<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
  header("Location: ../login.php");
  exit();
}

require_once "../Database/dbconnect.php";

$admin_id = $_SESSION['user_id'];

// Fetch admin info
$stmt = $conn->prepare("SELECT full_name, role, profile_image FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$admin_name = $admin['full_name'] ?? 'Admin User';
$admin_role = $admin['role'] ?? 'Administrator';
$profile_img = $admin['profile_image'] ?? "https://ui-avatars.com/api/?name=Admin+User&background=random";

// Validate customer ID
if (!isset($_GET['id'])) {
  die("Invalid customer ID.");
}

$customer_id = intval($_GET['id']);

// Fetch customer details
$sql = "
    SELECT u.*, 
        COUNT(r.reservation_id) AS total_rentals,
        SUM(r.total_cost) AS total_spent
    FROM users u
    LEFT JOIN reservations r ON u.user_id = r.user_id
    WHERE u.user_id = ? AND u.role = 'customer'
    GROUP BY u.user_id
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();

if (!$customer) {
  die("Customer not found.");
}

// Fetch rental history
$history_sql = "
    SELECT r.*, m.model, m.plate_number
    FROM reservations r
    JOIN motorcycles m ON r.motorcycle_id = m.motorcycle_id
    WHERE r.user_id = ?
    ORDER BY r.start_date DESC
";

$stmt = $conn->prepare($history_sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$history_res = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Customer Details — MotoRide Admin</title>
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
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
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
      justify-content: center;
      align-items: center;
      color: white;
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
      width: 32px;
      height: 32px;
      border-radius: 50%;
      border: 2px solid #374151;
    }

    /* CONTAINER */
    .container {
      max-width: 1200px;
      margin: 25px auto;
      background: white;
      padding: 28px;
      border-radius: 14px;
      border: 1px solid #e5e7eb;
    }

    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #e5e7eb;
      padding: 10px 18px;
      border-radius: 10px;
      text-decoration: none;
      color: #111827;
      font-weight: 500;
      margin-bottom: 20px;
    }

    .btn-back:hover {
      background: #d1d5db;
    }

    .section-title {
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 10px;
      text-transform: uppercase;
      color: #6b7280;
    }

    .detail-box {
      background: #f9fafb;
      padding: 18px;
      border-radius: 12px;
      border: 1px solid #e5e7eb;
      margin-bottom: 24px;
    }

    .row {
      margin-bottom: 10px;
      font-size: 15px;
    }

    .label {
      font-weight: 600;
      color: #374151;
    }

    /* TABLE */
    .data-card {
      background: white;
      padding: 0;
      border-radius: 12px;
      border: 1px solid #e5e7eb;
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th {
      background: #f3f4f6;
      padding: 14px 16px;
      font-size: 12px;
      text-transform: uppercase;
      color: #6b7280;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
    }

    td {
      padding: 14px 16px;
      border-bottom: 1px solid #f3f4f6;
      font-size: 14px;
    }

    .status-tag {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
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
      <img src="<?= $profile_img ?>">
    </div>
  </header>

  <div class="container">

    <a href="customer.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back</a>

    <h2 style="margin-bottom:15px;"><?= htmlspecialchars($customer['full_name']) ?></h2>

    <!-- CUSTOMER INFO -->
    <p class="section-title">Customer Information</p>
    <div class="detail-box">
      <div class="row"><span class="label">Email:</span> <?= htmlspecialchars($customer['email']) ?></div>
      <div class="row"><span class="label">Contact:</span> <?= htmlspecialchars($customer['contact_number']) ?></div>
      <div class="row"><span class="label">Address:</span> <?= htmlspecialchars($customer['address']) ?></div>
      <div class="row"><span class="label">Reward Points:</span> <?= htmlspecialchars($customer['reward_points']) ?></div>
      <div class="row"><span class="label">Total Rentals:</span> <?= $customer['total_rentals'] ?? 0 ?></div>
      <div class="row"><span class="label">Total Spent:</span> ₱<?= number_format($customer['total_spent'] ?? 0, 2) ?></div>
    </div>

    <!-- RENTAL HISTORY -->
    <p class="section-title">Rental History</p>
    <div class="data-card">
      <table>
        <thead>
          <tr>
            <th>Motorcycle</th>
            <th>Plate</th>
            <th>Start</th>
            <th>End</th>
            <th>Total Cost</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $history_res->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($row['model']) ?></td>
              <td><?= htmlspecialchars($row['plate_number']) ?></td>
              <td><?= $row['start_date'] ?></td>
              <td><?= $row['end_date'] ?></td>
              <td>₱<?= number_format($row['total_cost'], 2) ?></td>
              <td><span class="status-tag <?= $row['status'] ?>"><?= $row['status'] ?></span></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

  </div>

</body>

</html>