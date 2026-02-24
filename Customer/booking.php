<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'You must be logged in to book a motorcycle.']);
  exit;
}

require_once "../Database/dbconnect.php";

$user_id = $_SESSION['user_id'];

// --- Get POST data ---
$motorcycle_id = $_POST['motorcycle_id'] ?? null;
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';

// --- Validate inputs ---
if (!$motorcycle_id || !$start_date || !$end_date) {
  echo json_encode(['success' => false, 'message' => 'All fields are required.']);
  exit;
}

// --- Convert datetime-local to MySQL DATETIME ---
$start_date_db = date('Y-m-d H:i:s', strtotime($start_date));
$end_date_db   = date('Y-m-d H:i:s', strtotime($end_date));

// --- Validate date order ---
if (strtotime($end_date_db) <= strtotime($start_date_db)) {
  echo json_encode(['success' => false, 'message' => 'End date cannot be before or equal to start date.']);
  exit;
}

// --- Check if motorcycle is available ---
$stmt = $conn->prepare("
    SELECT rate_per_hour, status 
    FROM motorcycles 
    WHERE motorcycle_id = ? 
      AND status = 'Available'
    LIMIT 1
");
$stmt->bind_param("i", $motorcycle_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
  $rate_per_hour = (float)$row['rate_per_hour'];
} else {
  echo json_encode(['success' => false, 'message' => 'Motorcycle is not available.']);
  exit;
}
$stmt->close();

// --- Calculate total cost ---
$hours = max(1, ceil((strtotime($end_date_db) - strtotime($start_date_db)) / 3600));
$total_cost = $hours * $rate_per_hour;

// --- Insert reservation (status = Pending) ---
$stmt = $conn->prepare("
    INSERT INTO reservations 
    (user_id, motorcycle_id, start_date, end_date, total_cost, status, date_created) 
    VALUES (?, ?, ?, ?, ?, 'Pending', NOW())
");
$stmt->bind_param("iissd", $user_id, $motorcycle_id, $start_date_db, $end_date_db, $total_cost);

if ($stmt->execute()) {
  echo json_encode([
    'success' => true,
    'message' => 'Booking submitted and is pending admin approval!',
    'total_cost' => number_format($total_cost, 2)
  ]);
} else {
  echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
