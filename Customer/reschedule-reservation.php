<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

require_once "../Database/dbconnect.php";

$user_id = $_SESSION['user_id'];
$reservation_id = (int)($_GET['id'] ?? 0);

$success_message = '';
$current_status = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $new_start = $_POST['start_date'] ?? '';
  $new_end = $_POST['end_date'] ?? '';

  if ($reservation_id && $new_start && $new_end) {
    // Get motorcycle_id for this reservation
    $stmt = $conn->prepare("SELECT motorcycle_id FROM reservations WHERE reservation_id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $reservation_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      $motorcycle_id = $row['motorcycle_id'];
    }
    $stmt->close();

    // Update reservation dates and status
    $stmt = $conn->prepare("
            UPDATE reservations 
            SET start_date = ?, end_date = ?, status = CASE WHEN status = 'Pending' THEN 'Confirmed' ELSE status END 
            WHERE reservation_id = ? AND user_id = ?
        ");
    $stmt->bind_param("ssii", $new_start, $new_end, $reservation_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // Update motorcycle status to Rented
    if (isset($motorcycle_id)) {
      $stmt2 = $conn->prepare("UPDATE motorcycles SET status = 'Rented' WHERE motorcycle_id = ?");
      $stmt2->bind_param("i", $motorcycle_id);
      $stmt2->execute();
      $stmt2->close();
    }

    $success_message = "✅ Reservation rescheduled successfully.";
  }
}

// Fetch current reservation
$reservation = [];
if ($reservation_id) {
  $stmt = $conn->prepare("SELECT start_date, end_date, status FROM reservations WHERE reservation_id = ? AND user_id = ? LIMIT 1");
  $stmt->bind_param("ii", $reservation_id, $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    $reservation = $row;
    $current_status = $row['status'];
  }
  $stmt->close();
}


// Determine badge color
$status_colors = [
  'Pending' => '#fbbf24',   // yellow
  'Confirmed' => '#22c55e', // green
  'Approved' => '#2563eb',  // blue
  'Rejected' => '#ef4444',  // red
  'Completed' => '#6366f1', // indigo
];
$badge_color = $status_colors[$current_status] ?? '#9ca3af';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Reschedule Reservation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(4px);
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }

    .modal-card {
      background: #fff;
      border-radius: 2rem;
      padding: 2rem;
      width: 100%;
      max-width: 400px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
      position: relative;
      animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .modal-card h2 {
      text-align: center;
      font-size: 1.75rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: #111827;
    }

    .status-badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.875rem;
      font-weight: 600;
      color: #fff;
      text-align: center;
      margin-bottom: 2rem;
      display: flex;
      width: max-content;
      justify-content: center;
      align-items: center;
      margin: 0 auto;
    }

    .modal-card label {
      display: block;
      font-size: 0.875rem;
      margin-bottom: 0.25rem;
      color: #374151;
    }

    .modal-card input[type="datetime-local"] {
      width: 94%;
      padding: 0.5rem 0.75rem;
      border: 1px solid #d1d5db;
      border-radius: 0.75rem;
      margin-bottom: 1rem;
      font-size: 0.875rem;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .modal-card input[type="datetime-local"]:focus {
      outline: none;
      border-color: #22c55e;
      box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.3);
    }

    .modal-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 1rem;
    }

    .btn {
      border-radius: 0.75rem;
      font-weight: 600;
      font-size: 0.875rem;
      padding: 0.5rem 1rem;
      cursor: pointer;
      border: none;
      transition: all 0.2s ease;
    }

    .btn-submit {
      background: #22c55e;
      color: #fff;
      flex: 1;
      margin-right: 0.5rem;
    }

    .btn-submit:hover {
      background: #16a34a;
    }

    .btn-cancel {
      background: #e5e7eb;
      color: #374151;
      text-decoration: none;
      text-align: center;
      flex: 1;
      margin-left: 0.5rem;
    }

    .btn-cancel:hover {
      background: #d1d5db;
    }

    .close-btn {
      position: absolute;
      top: 0.75rem;
      right: 0.75rem;
      font-size: 1.25rem;
      color: #9ca3af;
      background: transparent;
      border: none;
      cursor: pointer;
    }

    .close-btn:hover {
      color: #6b7280;
    }

    .success-message {
      background: #d1fae5;
      color: #065f46;
      padding: 0.5rem 1rem;
      border-radius: 0.75rem;
      text-align: center;
      margin-bottom: 1rem;
      font-weight: 600;
      animation: fadeIn 0.5s ease;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>

<body>
  <div class="modal-card">
    <button class="close-btn" onclick="window.location.href='my-bookings.php'">✕</button>
    <h2>Reschedule Reservation</h2>

    <?php if ($current_status): ?>
      <div class="status-badge" style="background-color: <?= $badge_color ?>"><?= htmlspecialchars($current_status) ?></div>
    <?php endif; ?>

    <?php if ($success_message): ?>
      <div class="success-message"><?= $success_message ?></div>
      <script>
        setTimeout(() => {
          window.location.href = 'my-bookings.php';
        }, 3000);
      </script>
    <?php endif; ?>

    <form method="post">
      <label for="start_date">Start Date & Time</label>
      <input type="datetime-local" id="start_date" name="start_date"
        value="<?= htmlspecialchars($reservation['start_date'] ?? '') ?>" required>

      <label for="end_date">End Date & Time</label>
      <input type="datetime-local" id="end_date" name="end_date"
        value="<?= htmlspecialchars($reservation['end_date'] ?? '') ?>" required>

      <div class="modal-actions">
        <button type="submit" class="btn btn-submit">Reschedule</button>
        <a href="my-bookings.php" class="btn btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</body>

</html>