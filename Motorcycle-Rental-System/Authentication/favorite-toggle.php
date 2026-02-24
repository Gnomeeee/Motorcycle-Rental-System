<?php
session_start();
header('Content-Type: application/json'); // important

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

require_once "../Database/dbconnect.php";

$input = json_decode(file_get_contents('php://input'), true);
$motorcycle_id = intval($input['bike_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$motorcycle_id) {
  echo json_encode(['success' => false, 'message' => 'Invalid motorcycle']);
  exit;
}

// check if already favorite
$stmt = $conn->prepare("SELECT * FROM favorites WHERE user_id = ? AND motorcycle_id = ?");
$stmt->bind_param("ii", $user_id, $motorcycle_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
  // remove favorite
  $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND motorcycle_id = ?");
  $stmt->bind_param("ii", $user_id, $motorcycle_id);
  $stmt->execute();
  echo json_encode(['success' => true, 'action' => 'removed']);
} else {
  // add favorite
  $stmt = $conn->prepare("INSERT INTO favorites (user_id, motorcycle_id) VALUES (?, ?)");
  $stmt->bind_param("ii", $user_id, $motorcycle_id);
  $stmt->execute();
  echo json_encode(['success' => true, 'action' => 'added']);
}
exit;
