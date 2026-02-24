<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
  header("Location: ../login.php");
  exit();
}

require_once "../Database/dbconnect.php";

$message = "";
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  // SAFE POST READING (no warnings anymore)
  $model         = trim($_POST['model'] ?? '');
  $plate_number  = strtoupper(trim($_POST['plate_number'] ?? ''));
  $category      = trim($_POST['category'] ?? '');
  $rate_per_hour = floatval($_POST['rate_per_hour'] ?? 0);
  $status        = trim($_POST['status'] ?? '');
  $image_url     = NULL;

  // ===== VALIDATION =====
  if (empty($model) || empty($plate_number) || empty($category) || $rate_per_hour <= 0) {
    $message = '<div class="alert error">⚠ Please complete all fields correctly.</div>';
  } else {

    // ===== HANDLE IMAGE UPLOAD =====
    if (!empty($_FILES['image']['name'])) {

      $targetDir = "../uploads/motorcycles/";
      if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
      }

      $fileName = time() . "_" . preg_replace("/[^A-Za-z0-9.\-_]/", "", basename($_FILES["image"]["name"]));
      $targetFile = $targetDir . $fileName;

      $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
      $allowed = ["jpg", "jpeg", "png", "webp"];

      if (!in_array($fileType, $allowed)) {
        $message = '<div class="alert error">❌ Invalid image type. Use JPG, PNG or WEBP.</div>';
      } else {

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
          $image_url = "uploads/motorcycles/" . $fileName;
        } else {
          $message = '<div class="alert error">❌ Failed to upload image.</div>';
        }
      }
    }

    // ===== INSERT INTO DATABASE =====
    
    $stmt = $conn->prepare(
      "INSERT INTO motorcycles
        (model, plate_number, category, rate_per_hour, status, image_url)
        VALUES (?, ?, ?, ?, ?, ?)"
    );

    // Determine actual status before inserting
    $actual_status = $status; // default from admin input

    if (strtolower($status) === 'available') {
      // Check if there is a current booking (unlikely for new motorcycle, but for safety)
      $stmtCheck = $conn->prepare("
        SELECT COUNT(*) AS cnt 
        FROM reservations 
        WHERE motorcycle_id = ? 
          AND status IN ('pending','confirmed') 
          AND NOW() BETWEEN start_date AND end_date
    ");
      $stmtCheck->bind_param("i", $motorcycle_id_temp); // temp variable
      $motorcycle_id_temp = 0; // new bike hasn't got ID yet
      $stmtCheck->execute();
      $resCheck = $stmtCheck->get_result();
      $cnt = $resCheck->fetch_assoc()['cnt'] ?? 0;
      $stmtCheck->close();

      if ($cnt > 0) {
        $actual_status = 'Rented';
      }
    }

    $stmt->bind_param(
      "sssdss",
      $model,
      $plate_number,
      $category,
      $rate_per_hour,
      $actual_status,
      $image_url
    );


    if ($stmt->execute()) {
      $message = '<div class="alert success">✔ Motorcycle added successfully! Redirecting...</div>';
      $success = true;
    } else {
      $message = '<div class="alert error">❌ Database Error: ' . $stmt->error . '</div>';
    }

    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Add Motorcycle</title>

  <style>
    body {
      font-family: "Inter", sans-serif;
      background: #f3f4f6;
      padding: 40px;
    }

    .form-container {
      max-width: 650px;
      margin: auto;
      background: #fff;
      padding: 32px;
      border-radius: 14px;
      box-shadow: 0 5px 10px rgba(0, 0, 0, 0.07);
    }

    h2 {
      margin-bottom: 18px;
      border-left: 6px solid #ff6a00;
      padding-left: 10px;
      font-size: 26px;
      font-weight: 700;
      color: #333;
    }

    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-weight: 600;
    }

    .success {
      background: #d1fae5;
      color: #065f46;
    }

    .error {
      background: #fee2e2;
      color: #991b1b;
    }

    .form-group {
      margin-bottom: 18px;
    }

    label {
      font-weight: 600;
      margin-bottom: 6px;
      display: block;
    }

    input,
    select {
      width: 100%;
      padding: 13px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 15px;
    }

    input:focus,
    select:focus {
      border-color: #ff6a00;
      outline: none;
      box-shadow: 0 0 4px rgba(255, 106, 0, 0.3);
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 25px;
    }

    .btn-submit,
    .btn-cancel {
      padding: 12px 20px;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s ease;
    }

    .btn-submit {
      background: #ff6a00;
      color: white;
    }

    .btn-submit:hover {
      background: #e55700;
    }

    .btn-cancel {
      background: #6b7280;
      color: white;
    }

    .btn-cancel:hover {
      background: #4b5563;
    }
  </style>
</head>

<body>

  <div class="form-container">
    <h2>Add Motorcycle</h2>

    <?= $message ?>

    <form method="POST" enctype="multipart/form-data">

      <div class="form-group">
        <label>Model Name</label>
        <input type="text" name="model" placeholder="e.g., Honda Click 125i" required>
      </div>

      <div class="form-group">
        <label>Plate Number</label>
        <input type="text" name="plate_number" placeholder="e.g., ABC 1234" required>
      </div>

      <div class="form-group">
        <label>Category</label>
        <select name="category" required>
          <option value="">-- Select Category --</option>
          <option value="Scooter">Scooter</option>
          <option value="Underbone">Underbone</option>
          <option value="Standard">Standard</option>
          <option value="Automatic">Automatic</option>
          <option value="Sport">Sport</option>
        </select>
      </div>

      <div class="form-group">
        <label>Rate Per Hour (₱)</label>
        <input type="number" step="0.01" name="rate_per_hour" required>
      </div>

      <div class="form-group">
        <label>Status</label>
        <select name="status" required>
          <option value="Available">Available</option>
          <option value="Maintenance">Maintenance</option>
          <option value="Unavailable">Unavailable</option>
        </select>
      </div>

      <div class="form-group">
        <label>Motorcycle Image</label>
        <input type="file" name="image" accept="image/*">
      </div>

      <div class="form-actions">
        <a href="motorcycle.php" class="btn-cancel">Cancel</a>
        <button type="submit" class="btn-submit">Add Motorcycle</button>
      </div>

    </form>
  </div>

  <?php if ($success): ?>
    <script>
      setTimeout(() => {
        window.location.href = "motorcycle.php";
      }, 1500);
    </script>
  <?php endif; ?>

</body>

</html>