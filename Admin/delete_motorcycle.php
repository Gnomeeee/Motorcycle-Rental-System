<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
  header("Location: ../login.php");
  exit();
}

require_once "../Database/dbconnect.php";

$bike_id = (int)($_GET['id'] ?? 0);

if ($bike_id > 0) {
  // SECURITY NOTE: It is critical to ensure no active reservations exist before deleting.
  // For this simple script, we'll just delete, but in a real app, you must check for FK constraints or active records.

  // Check for active reservations (recommended real-world check)
  $stmt_check = $conn->prepare("SELECT COUNT(*) FROM reservations WHERE motorcycle_id = ? AND status IN ('Active', 'Pending')");
  $stmt_check->bind_param("i", $bike_id);
  $stmt_check->execute();
  $count = $stmt_check->get_result()->fetch_row()[0];
  $stmt_check->close();

  if ($count > 0) {
    // Cannot delete motorcycle with active reservations
    header("Location: motorcycles.php?error=active_reservations");
    exit();
  }

  // Proceed with deletion
  $stmt = $conn->prepare("DELETE FROM motorcycles WHERE motorcycle_id = ?");
  $stmt->bind_param("i", $bike_id);

  if ($stmt->execute()) {
    // Success
    header("Location: motorcycle.php?success=delete");
    exit();
  } else {
    // Error (e.g., if other dependent records exist and cascade is not set)
    header("Location: motorcycle.php?error=deletion_failed");
    exit();
  }
} else {
  // Invalid ID
  header("Location: motorcycle.php?error=invalid_id");
}

header("Location: motorcycle.php");
exit();
