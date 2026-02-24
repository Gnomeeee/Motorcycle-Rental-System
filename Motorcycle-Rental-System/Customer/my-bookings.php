<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

require_once "../Database/dbconnect.php";

$user_id = $_SESSION['user_id'];

// Profile Image
$upload_folder = "../Assets/Uploads/";
$default_avatar = "../Assets/Images/download.png";
$profile_img = $default_avatar;

$stmt = $conn->prepare("SELECT full_name, role, profile_image FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
  $_SESSION['user_name'] = $row['full_name'];
  $_SESSION['role'] = $row['role'];
  if (!empty($row['profile_image']) && file_exists($upload_folder . $row['profile_image'])) {
    $profile_img = $upload_folder . htmlspecialchars($row['profile_image']);
  }
}
$stmt->close();

// Fetch reservations
$reservations = [];
$stmt = $conn->prepare("
    SELECT r.reservation_id, r.motorcycle_id, r.start_date, r.end_date, r.total_cost, r.status, r.date_created,
           m.model, m.image_url
    FROM reservations r
    JOIN motorcycles m ON r.motorcycle_id = m.motorcycle_id
    WHERE r.user_id = ?
    ORDER BY r.start_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $reservations[] = $row;
}
$stmt->close();

$placeholder = "../Assets/Images/bike-placeholder.jpg";
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>My Reservations — MotoRide</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="./Styles/dashboard.css">

  <style>
    .container {
      max-width: 900px;
      margin: 20px auto;
      padding: 0 16px;
    }

    .booking-card {
      display: flex;
      align-items: center;
      gap: 16px;
      background: #fff;
      border-radius: 12px;
      padding: 12px 16px;
      margin-bottom: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .booking-card img {
      width: 100px;
      height: 80px;
      object-fit: cover;
      border-radius: 8px;
    }

    .booking-info {
      flex: 1;
    }

    .booking-info .model {
      font-weight: 700;
      font-size: 16px;
    }

    .booking-info .dates,
    .booking-info .total {
      font-size: 14px;
      color: #555;
      margin-top: 4px;
    }

    .booking-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
    }

    .booking-actions a {
      padding: 6px 12px;
      border-radius: 6px;
      text-decoration: none;
      font-size: 13px;
    }

    .view-btn {
      background: #e2e8f0;
      color: #111;
    }

    .cancel-btn {
      background: #ef4444;
      color: white;
    }

    .reschedule-btn {
      background: #f59e0b;
      color: white;
    }

    .status {
      font-size: 12px;
      padding: 4px 8px;
      border-radius: 6px;
      font-weight: 600;
    }

    .status.Pending {
      background: #fef3c7;
      color: #b45309;
    }

    .status.Approved {
      background: #d1fae5;
      color: #065f46;
    }

    .status.Rejected {
      background: #fee2e2;
      color: #991b1b;
    }

    .status.Completed {
      background: #dbeafe;
      color: green;
    }
  </style>
</head>

<body>
  <div class="topbar">
    <div class="brand">
      <div class="logo"><img src="../Assets/Svg/motorcycle-svgrepo-com.svg" alt=""></div>
      <div>
        <div style="font-weight:700">MotoRide</div>
        <div style="font-size:12px;color:var(--muted)">Customer Portal</div>
      </div>
    </div>

    <div class="top-buttons">
      <a href="dashboard.php" class="top-btn <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">Browse Bikes</a>
      <a href="my-bookings.php" class="top-btn <?= $current_page == 'my-bookings.php' ? 'active' : '' ?>">My Bookings</a>
      <a href="profile.php" class="top-btn <?= $current_page == 'profile.php' ? 'active' : '' ?>">Profile</a>
    </div>

    <div class="profile">
      <img src="<?= $profile_img ?>" onerror="this.src='<?= $default_avatar ?>'">
      <div class="profile-info">
        <div class="name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
        <div class="role"><?= htmlspecialchars($_SESSION['role']) ?></div>
        <a href="logout.php" class="logout-btn">Logout</a>
      </div>
    </div>
  </div>

  <div class="container">
    <h2>My Reservations</h2>

    <?php if (empty($reservations)): ?>
      <p>No reservations found.</p>

    <?php else: ?>
      <?php foreach ($reservations as $r):
        $bike_img = $placeholder;
        if (!empty($r['image_url']) && file_exists("../" . $r['image_url'])) {
          $bike_img = "../" . htmlspecialchars($r['image_url']);
        }
        $status = $r['status'];
      ?>
        <div class="booking-card">
          <img src="<?= $bike_img ?>" alt="Bike">

          <div class="booking-info">
            <div class="model"><?= htmlspecialchars($r['model']) ?></div>
            <div class="dates">📅 <?= $r['start_date'] ?> → <?= $r['end_date'] ?></div>
            <div class="total">Total: ₱<?= number_format($r['total_cost'], 2) ?></div>
          </div>

          <div class="booking-actions">
            <span class="status <?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span>

            <a href="view-reservation.php?id=<?= $r['reservation_id'] ?>" class="view-btn">View Details</a>

            <?php if ($status == 'Pending'): ?>
              <!-- Customer CAN manage booking -->
              <a href="reschedule-reservation.php?id=<?= $r['reservation_id'] ?>" class="reschedule-btn">Reschedule</a>
              <a href="cancel-reservation.php?id=<?= $r['reservation_id'] ?>" class="cancel-btn">Cancel</a>

            <?php elseif ($status == 'Approved'): ?>
              <!-- Customer CANNOT modify -->
              <span style="font-size:12px;color:#0f766e;">✔ Approved — Rider is on the way</span>

            <?php elseif ($status == 'Rejected'): ?>
              <span style="font-size:12px;color:#ef4444;">❌ Reservation Rejected</span>

            <?php elseif ($status == 'Completed'): ?>
              <a href="dashboard.php?motorcycle_id=<?= $r['motorcycle_id'] ?>" class="view-btn">Book Again</a>

            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</body>
</html>
