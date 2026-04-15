<?php
session_start();
require_once "../Database/dbconnect.php";

header("Content-Type: application/json");

/* =================================================
   Auth check
================================================= */
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "User not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];

/* =================================================
   Read POST fields
================================================= */
$motorcycle_id = isset($_POST['motorcycle_id']) ? (int)$_POST['motorcycle_id'] : 0;
$start_date    = trim($_POST['start_date'] ?? '');   // "YYYY-MM-DD HH:MM:00"
$end_date      = trim($_POST['end_date']   ?? '');   // "YYYY-MM-DD HH:MM:00"
$full_name     = trim($_POST['full_name']  ?? '');

if (!$motorcycle_id || !$start_date || !$end_date || !$full_name) {
    echo json_encode(["success" => false, "message" => "Missing required fields."]);
    exit;
}

/* =================================================
   Validate datetime format (YYYY-MM-DD HH:MM:SS)
================================================= */
$start_ts = strtotime($start_date);
$end_ts   = strtotime($end_date);

if (!$start_ts || !$end_ts) {
    echo json_encode(["success" => false, "message" => "Invalid date/time format."]);
    exit;
}

/* =================================================
   Rule 1 — End must be after Start
================================================= */
if ($end_ts <= $start_ts) {
    echo json_encode(["success" => false, "message" => "End time must be later than start time."]);
    exit;
}

/* =================================================
   Rule 2 — Minimum rental duration: 1 hour
================================================= */
$duration_hours = ($end_ts - $start_ts) / 3600;
if ($duration_hours < 1) {
    echo json_encode(["success" => false, "message" => "Minimum rental duration is 1 hour."]);
    exit;
}

/* =================================================
   Rule 3 — Start time must not be in the past
   (5-minute grace period for network/clock delay)
================================================= */
$grace_seconds = 300; // 5 minutes
if ($start_ts < time() - $grace_seconds) {
    echo json_encode(["success" => false, "message" => "Start time cannot be in the past."]);
    exit;
}

/* =================================================
   Rule 4 — Bookings cannot be made more than
   30 days in advance (optional business rule)
================================================= */
$max_advance_seconds = 30 * 24 * 3600;
if ($start_ts > time() + $max_advance_seconds) {
    echo json_encode(["success" => false, "message" => "Bookings can only be made up to 30 days in advance."]);
    exit;
}

/* =================================================
   Fetch motorcycle details
================================================= */
$stmt = $conn->prepare("SELECT rate_per_hour, status FROM motorcycles WHERE motorcycle_id = ? LIMIT 1");
$stmt->bind_param("i", $motorcycle_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Motorcycle not found."]);
    exit;
}

$bike = $result->fetch_assoc();
$stmt->close();

if (strtolower($bike['status']) !== 'available') {
    echo json_encode(["success" => false, "message" => "This motorcycle is currently not available."]);
    exit;
}

/* =================================================
   Check for overlapping reservations on the
   same motorcycle (Pending or Approved only)
================================================= */
$conflict_sql = "
    SELECT reservation_id
    FROM   reservations
    WHERE  motorcycle_id = ?
      AND  status IN ('Pending', 'Approved')
      AND  start_date < ?
      AND  end_date   > ?
    LIMIT 1
";
$stmt = $conn->prepare($conflict_sql);
$stmt->bind_param("iss", $motorcycle_id, $end_date, $start_date);
$stmt->execute();
$conflict = $stmt->get_result();
$stmt->close();

if ($conflict->num_rows > 0) {
    echo json_encode([
        "success" => false,
        "message" => "This motorcycle is already booked for part of the selected time slot. Please choose a different time."
    ]);
    exit;
}

/* =================================================
   Calculate total cost
   (server-side calculation — do NOT trust the
    client-sent total_cost value)
================================================= */
$total_cost = round($duration_hours * (float)$bike['rate_per_hour'], 2);

/* =================================================
   Insert reservation
================================================= */
$stmt = $conn->prepare("
    INSERT INTO reservations
        (user_id, motorcycle_id, start_date, end_date, total_cost, status)
    VALUES (?, ?, ?, ?, ?, 'Pending')
");
$stmt->bind_param("iissd", $user_id, $motorcycle_id, $start_date, $end_date, $total_cost);

if ($stmt->execute()) {
    $reservation_id = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        "success"        => true,
        "message"        => "Reservation successful.",
        "reservation_id" => $reservation_id,
        "total_cost"     => $total_cost,
        "duration_hours" => round($duration_hours, 2)
    ]);
} else {
    $error = $conn->error;
    $stmt->close();

    echo json_encode([
        "success" => false,
        "message" => "Database error. Please try again."
        // Don't expose $error to the client in production
    ]);
}
?>