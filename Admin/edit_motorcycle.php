<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
  header("Location: ../login.php");
  exit();
}

require_once "../Database/dbconnect.php";

$message = '';
$bike_id = (int)($_GET['id'] ?? 0);

// --- 1. Fetch motorcycle info ---
if ($bike_id > 0) {
  $stmt = $conn->prepare("SELECT motorcycle_id, model, category, rate_per_hour, status, plate_number 
                            FROM motorcycles WHERE motorcycle_id = ?");
  $stmt->bind_param("i", $bike_id);
  $stmt->execute();
  $bike = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$bike) {
    $message = '<div class="alert error">Motorcycle not found.</div>';
    $bike = ['model' => '', 'category' => '', 'rate_per_hour' => 0, 'status' => 'Available', 'plate_number' => ''];
  }
} else {
  $message = '<div class="alert error">Invalid motorcycle ID.</div>';
  $bike = ['model' => '', 'category' => '', 'rate_per_hour' => 0, 'status' => 'Available', 'plate_number' => ''];
}

// --- 2. Handle Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

  $model = trim($_POST['model']);
  $plate_number = trim($_POST['plate_number']);
  $category = trim($_POST['category']);
  $rate_per_hour = (float)$_POST['rate_per_hour'];
  $status = trim($_POST['status']);

  if (empty($model) || empty($plate_number) || empty($category) || $rate_per_hour <= 0) {
    $message = '<div class="alert error">⚠ Please complete all fields correctly.</div>';
  } else {

    $stmt = $conn->prepare("UPDATE motorcycles 
                                SET model=?, plate_number=?, category=?, rate_per_hour=?, status=? 
                                WHERE motorcycle_id=?");

    // Fixed bind_param: 6 variables, type string: s s s d s i
    $stmt->bind_param("sssdsi", $model, $plate_number, $category, $rate_per_hour, $status, $bike_id);

    if ($stmt->execute()) {
      $message = '<div class="alert success">✔ Motorcycle updated successfully!</div>';

      // Update form values live
      $bike['model'] = $model;
      $bike['plate_number'] = $plate_number;
      $bike['category'] = $category;
      $bike['rate_per_hour'] = $rate_per_hour;
      $bike['status'] = $status;
    } else {
      $message = '<div class="alert error">❌ Error: ' . $stmt->error . '</div>';
    }

    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Edit Motorcycle</title>
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
      text-decoration: none;
      display: inline-block;
    }

    .btn-cancel:hover {
      background: #4b5563;
    }
  </style>
</head>

<body>
  <div class="form-container">
    <h2>Edit Motorcycle</h2>

    <?= $message ?>

    <form method="POST">
      <div class="form-group">
        <label>Model Name</label>
        <input type="text" name="model" value="<?= htmlspecialchars($bike['model']) ?>" required>
      </div>

      <div class="form-group">
        <label>Plate Number</label>
        <input type="text" name="plate_number" value="<?= htmlspecialchars($bike['plate_number']) ?>" required>
      </div>

      <div class="form-group">
        <label>Category</label>
        <select name="category" required>
          <?php
          $categories = ["Scooter", "Underbone", "Standard", "Automatic", "Sport"];
          foreach ($categories as $cat) {
            $selected = ($bike['category'] == $cat) ? "selected" : "";
            echo "<option value='$cat' $selected>$cat</option>";
          }
          ?>
        </select>
      </div>

      <div class="form-group">
        <label>Rate Per Hour (₱)</label>
        <input type="number" step="0.01" name="rate_per_hour" value="<?= htmlspecialchars($bike['rate_per_hour']) ?>" required>
      </div>

      <div class="form-group">
        <label>Status</label>
        <select name="status" required>
          <option value="Available" <?= $bike['status'] == 'Available' ? 'selected' : '' ?>>Available</option>
          <option value="Maintenance" <?= $bike['status'] == 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
          <option value="Unavailable" <?= $bike['status'] == 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
        </select>
      </div>

      <div class="form-actions">
        <a href="motorcycle.php" class="btn-cancel">Cancel</a>
        <button type="submit" class="btn-submit">Save Changes</button>
      </div>
    </form>
  </div>
</body>

</html>