<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
  header("Location: ../login.php");
  exit();
}

require_once "../Database/dbconnect.php";

$reservation_id = (int)($_POST['reservation_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($reservation_id && $action) {
  // Begin transaction
  $conn->begin_transaction();
  try {
    // Fetch reservation
    $stmt = $conn->prepare("SELECT status, motorcycle_id FROM reservations WHERE reservation_id = ? LIMIT 1");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $reservation = $res->fetch_assoc();
    $stmt->close();

    if ($reservation) {
      $motorcycle_id = $reservation['motorcycle_id'];
      $current_status = strtolower(trim($reservation['status']));

      if ($action === 'approve' && $current_status === 'pending') {
        $stmt = $conn->prepare("UPDATE reservations SET status = 'Approved' WHERE reservation_id = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $stmt->close();

        // Set motorcycle status to Rented
        $stmt2 = $conn->prepare("UPDATE motorcycles SET status = 'Rented' WHERE motorcycle_id = ?");
        $stmt2->bind_param("i", $motorcycle_id);
        $stmt2->execute();
        $stmt2->close();

        $_SESSION['message'] = "✅ Reservation approved.";
      } elseif ($action === 'reject' && $current_status === 'pending') {
        $stmt = $conn->prepare("UPDATE reservations SET status = 'Rejected' WHERE reservation_id = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $stmt->close();

        // Set motorcycle back to Available
        $stmt2 = $conn->prepare("UPDATE motorcycles SET status = 'Available' WHERE motorcycle_id = ?");
        $stmt2->bind_param("i", $motorcycle_id);
        $stmt2->execute();
        $stmt2->close();

        $_SESSION['message'] = "❌ Reservation rejected.";
      } elseif ($action === 'completed' && $current_status === 'approved') {
        $stmt = $conn->prepare("UPDATE reservations SET status = 'Completed' WHERE reservation_id = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $stmt->close();

        // Set motorcycle back to Available
        $stmt2 = $conn->prepare("UPDATE motorcycles SET status = 'Available' WHERE motorcycle_id = ?");
        $stmt2->bind_param("i", $motorcycle_id);
        $stmt2->execute();
        $stmt2->close();

        $_SESSION['message'] = "✅ Reservation completed.";
      } elseif ($action === 'cancel' && in_array($current_status, ['pending', 'approved', 'completed'])) {
        // Cancel reservation
        $stmt = $conn->prepare("UPDATE reservations SET status = 'Cancelled' WHERE reservation_id = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $stmt->close();

        // Set motorcycle status to Available
        $stmt2 = $conn->prepare("UPDATE motorcycles SET status = 'Available' WHERE motorcycle_id = ?");
        $stmt2->bind_param("i", $motorcycle_id);
        $stmt2->execute();
        $stmt2->close();

        $_SESSION['message'] = "⚠️ Reservation cancelled. Motorcycle is now available.";
      } else {
        $_SESSION['message'] = "⚠️ Action not allowed for this reservation status.";
      }

      $conn->commit();
    } else {
      $_SESSION['message'] = "❌ Reservation not found.";
    }
  } catch (Exception $e) {
    $conn->rollback();
    $_SESSION['message'] = "❌ Error updating reservation: " . $e->getMessage();
  }
}

header("Location: reservations.php");
exit();
