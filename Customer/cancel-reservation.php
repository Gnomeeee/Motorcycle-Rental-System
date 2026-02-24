<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

require_once "../Database/dbconnect.php";

$user_id = $_SESSION['user_id'];
$reservation_id = (int)($_GET['id'] ?? 0);

if ($reservation_id > 0) {
  // Begin transaction
  $conn->begin_transaction();

  try {
    // Fetch reservation and motorcycle
    $stmt = $conn->prepare("SELECT status, motorcycle_id FROM reservations WHERE reservation_id = ? AND user_id = ? LIMIT 1");
    $stmt->bind_param("ii", $reservation_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $reservation = $res->fetch_assoc();
    $stmt->close();

    if ($reservation) {
      $status = strtolower(trim($reservation['status']));
      $motorcycle_id = $reservation['motorcycle_id'];

      // Update reservation status to Cancelled regardless of current status
      $update = $conn->prepare("UPDATE reservations SET status = 'Cancelled' WHERE reservation_id = ?");
      $update->bind_param("i", $reservation_id);
      $update->execute();
      $update->close();

      // Only set motorcycle back to Available if the bike is not already available
      if (in_array($status, ['pending', 'approved'])) {
        $update_bike = $conn->prepare("UPDATE motorcycles SET status = 'Available' WHERE motorcycle_id = ?");
        $update_bike->bind_param("i", $motorcycle_id);
        $update_bike->execute();
        $update_bike->close();
      }

      $conn->commit();
      $_SESSION['message'] = "✅ Reservation cancelled successfully.";
    } else {
      $_SESSION['message'] = "❌ Reservation not found.";
    }
  } catch (Exception $e) {
    $conn->rollback();
    $_SESSION['message'] = "❌ Error cancelling reservation: " . $e->getMessage();
  }
}

header('Location: my-bookings.php');
exit;
