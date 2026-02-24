<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

require_once "../Database/dbconnect.php";
$user_id = $_SESSION['user_id'];
$reservation_id = (int)($_GET['id'] ?? 0);

$reservation = [];
if ($reservation_id) {
  $stmt = $conn->prepare("
        SELECT r.*, m.model, m.image_url, m.category
        FROM reservations r
        JOIN motorcycles m ON r.motorcycle_id = m.motorcycle_id
        WHERE r.reservation_id = ? AND r.user_id = ?
        LIMIT 1
    ");
  $stmt->bind_param("ii", $reservation_id, $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    $reservation = $row;
  }
  $stmt->close();
}

$placeholder = "../Assets/Images/bike-placeholder.jpg";
$bike_img = (!empty($reservation['image_url']) && file_exists("../" . $reservation['image_url'])) ? "../" . $reservation['image_url'] : $placeholder;

// Status colors
$status_colors = [
  'Pending' => '#f59e0b',
  'Approved' => '#2563eb',
  'Rejected' => '#ef4444',
  'Completed' => '#22c55e',
  'Cancelled' => '#6b7280'
];
$badge_color = $status_colors[$reservation['status']] ?? '#9ca3af';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Reservation Details</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: #f3f4f6;
      margin: 0;
      padding: 0;
    }

    .container {
      max-width: 600px;
      margin: 50px auto;
      padding: 20px;
    }

    .card {
      background: #fff;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
      text-align: center;
    }

    .card img {
      width: 100%;
      height: 220px;
      object-fit: cover;
      border-radius: 12px;
      margin-bottom: 16px;
    }

    .card h2 {
      font-size: 1.75rem;
      margin-bottom: 8px;
    }

    .card .category {
      font-size: 0.9rem;
      color: #6b7280;
      margin-bottom: 16px;
    }

    .card .info {
      text-align: left;
      margin-bottom: 12px;
    }

    .card .info p {
      margin: 6px 0;
      font-size: 0.95rem;
      color: #374151;
    }

    .status-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 9999px;
      font-weight: 600;
      color: #fff;
      background-color: <?= $badge_color ?>;
      margin-top: 8px;
      font-size: 0.875rem;
    }

    .back-btn {
      display: inline-block;
      margin-top: 20px;
      padding: 10px 20px;
      border-radius: 12px;
      background: #2563eb;
      color: #fff;
      text-decoration: none;
      font-weight: 500;
      transition: 0.2s;
    }

    .back-btn:hover {
      background: #1d4ed8;
    }
  </style>
</head>

<body>
  <div class="container">
    <?php if ($reservation): ?>
      <div class="card">
        <img src="<?= $bike_img ?>" alt="<?= htmlspecialchars($reservation['model']) ?>">
        <h2><?= htmlspecialchars($reservation['model']) ?></h2>
        <div class="category"><?= htmlspecialchars($reservation['category'] ?? '') ?></div>

        <div class="info">
          <p><strong>Start:</strong> <?= date('Y-m-d H:i', strtotime($reservation['start_date'])) ?></p>
          <p><strong>End:</strong> <?= date('Y-m-d H:i', strtotime($reservation['end_date'])) ?></p>
          <p><strong>Total:</strong> ₱<?= number_format($reservation['total_cost'], 2) ?></p>
          <p><strong>Created on:</strong> <?= date('Y-m-d H:i', strtotime($reservation['date_created'])) ?></p>
        </div>

        <div class="status-badge"><?= htmlspecialchars($reservation['status']) ?></div>
        <br>
        <a href="my-bookings.php" class="back-btn">← Back to My Reservations</a>
      </div>
    <?php else: ?>
      <p style="text-align:center; color:#ef4444;">Reservation not found.</p>
      <div style="text-align:center;">
        <a href="my-bookings.php" class="back-btn">← Back</a>
      </div>
    <?php endif; ?>
  </div>
</body>

</html>