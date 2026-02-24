<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../login.php");
  exit;
}

require_once "../Database/dbconnect.php";

$user_id = $_SESSION['user_id'];

/* ============================
      GET USER INFORMATION
   ============================ */
$stmt = $conn->prepare("
  SELECT full_name, email, contact_number, address, profile_image, role, reward_points 
  FROM users 
  WHERE user_id = ? LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

/* ============================
    FIXED IMAGE DIRECTORY PATH
   ============================ */
$upload_folder = "../Assets/Uploads/";

if (!is_dir($upload_folder)) {
  mkdir($upload_folder, 0777, true);
}

$default_avatar = "../Assets/Images/download.png";

$profile_img = (!empty($user['profile_image']) && file_exists($upload_folder . $user['profile_image']))
  ? $upload_folder . htmlspecialchars($user['profile_image'])
  : $default_avatar;

$current_page = basename($_SERVER['PHP_SELF']);

$success_msg = '';

/* ============================
          UPDATE PROFILE
   ============================ */
if ($_SERVER['REQUEST_METHOD'] === "POST") {
  $full_name = $_POST['full_name'];
  $email     = $_POST['email'];
  $contact   = $_POST['contact_number'];
  $address   = $_POST['address'];

  if (!empty($_FILES['profile_image']['name'])) {
    $img_name = time() . "_" . basename($_FILES['profile_image']['name']);
    $target = $upload_folder . $img_name;
    move_uploaded_file($_FILES['profile_image']['tmp_name'], $target);

    $stmt = $conn->prepare("
      UPDATE users 
      SET full_name=?, email=?, contact_number=?, address=?, profile_image=? 
      WHERE user_id=?
    ");
    $stmt->bind_param("sssssi", $full_name, $email, $contact, $address, $img_name, $user_id);
  } else {
    $stmt = $conn->prepare("
      UPDATE users 
      SET full_name=?, email=?, contact_number=?, address=? 
      WHERE user_id=?
    ");
    $stmt->bind_param("ssssi", $full_name, $email, $contact, $address, $user_id);
  }

  if ($stmt->execute()) {
    $success_msg = "Your profile has been updated successfully!";
    // Refresh user data
    $stmt = $conn->prepare("
      SELECT full_name, email, contact_number, address, profile_image, role, reward_points 
      FROM users 
      WHERE user_id = ? LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();
    $profile_img = (!empty($user['profile_image']) && file_exists($upload_folder . $user['profile_image']))
      ? $upload_folder . htmlspecialchars($user['profile_image'])
      : $default_avatar;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Profile — MotoRide</title>
  <link rel="stylesheet" href="./Styles/dashboard.css">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      background: #f7f7f7;
      font-family: Inter;
    }

    .profile-container {
      max-width: 1200px;
      margin: 40px auto;
      display: flex;
      gap: 30px;
      padding: 0 20px;
    }

    .card {
      background: white;
      border-radius: 14px;
      padding: 25px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      flex: 1;
    }

    .profile-pic {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
      margin-bottom: 20px;
    }

    .profile-pic img {
      width: 110px;
      height: 110px;
      object-fit: cover;
      border-radius: 50%;
      border: 2px solid #ddd;
    }

    .upload-label {
      display: inline-block;
      padding: 6px 12px;
      background-color: #ff6a00;
      color: white;
      border-radius: 6px;
      font-size: 14px;
      cursor: pointer;
      font-weight: 500;
    }

    .input-group {
      margin-bottom: 15px;
    }

    .input-group label {
      display: block;
      font-size: 14px;
      margin-bottom: 4px;
      color: #444;
    }

    .input-group input,
    .input-group textarea {
      width: 100%;
      padding: 10px;
      border-radius: 8px;
      border: 1px solid #ddd;
      font-size: 14px;
    }

    textarea {
      height: 80px;
      resize: none;
    }

    .save-btn {
      width: 100%;
      padding: 12px;
      border: 0;
      background: #ff6a00;
      color: white;
      font-size: 15px;
      font-weight: 600;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 10px;
    }

    .right-side {
      width: 380px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .small-card {
      background: #fff;
      border-radius: 14px;
      padding: 18px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .membership-card {
      background: linear-gradient(90deg, #ff7300, #ff4800);
      border-radius: 14px;
      padding: 22px;
      color: white;
    }

    .membership-card h2 {
      margin: 0;
    }

    .benefits-btn {
      margin-top: 12px;
      width: 100%;
      padding: 10px;
      background: white;
      border: 0;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
    }

    .success-msg {
      background-color: #d1fae5;
      color: #065f46;
      padding: 10px 15px;
      border-radius: 6px;
      margin-bottom: 15px;
      text-align: center;
      font-weight: 500;
    }
  </style>
</head>

<body>

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="brand">
      <img src="../Assets/Svg/motorcycle-svgrepo-com.svg" class="logo">
      <div>
        <div style="font-weight:700">MotoRide</div>
        <div style="font-size:12px;color:var(--muted)">Customer Portal</div>
      </div>
    </div>

    <div class="top-buttons">
      <a href="dashboard.php" class="top-btn <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">Browse Bikes</a>
      <a href="my-bookings.php" class="top-btn <?= $current_page == 'my-bookings.php' ? 'active' : '' ?>">My Bookings</a>
      <a href="profile.php" class="top-btn <?= $current_page == 'profile.php' ? 'active' : '' ?>">Profile</a>
    </div>

    <div class="profile">
      <img src="<?= $profile_img ?>" alt="avatar" onerror="this.src='<?= $default_avatar ?>'">
      <div class="profile-info">
        <div class="name"><?= htmlspecialchars($user['full_name']) ?></div>
        <div class="role"><?= htmlspecialchars($user['role']) ?></div>
        <a href="logout.php" class="logout-btn">Logout</a>
      </div>
    </div>
  </div>

  <!-- MAIN BODY -->
  <div class="profile-container">

    <!-- LEFT -->
    <div class="card">
      <h3>Personal Information</h3>
      <p>Update your personal details</p>

      <?php if ($success_msg): ?>
        <div class="success-msg"><?= $success_msg ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">

        <div class="profile-pic">
          <img src="<?= $profile_img ?>" alt="profile" onerror="this.src='<?= $default_avatar ?>'">
          <label for="profile_image" class="upload-label">Choose a new photo</label>
          <input type="file" name="profile_image" id="profile_image" style="display:none;">
        </div>

        <div class="input-group">
          <label>Full Name</label>
          <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>">
        </div>

        <div class="input-group">
          <label>Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>">
        </div>

        <div class="input-group">
          <label>Contact Number</label>
          <input type="text" name="contact_number" value="<?= htmlspecialchars($user['contact_number']) ?>">
        </div>

        <div class="input-group">
          <label>Address</label>
          <textarea name="address"><?= htmlspecialchars($user['address']) ?></textarea>
        </div>

        <button class="save-btn">Save Changes</button>
      </form>
    </div>

    <!-- RIGHT SIDE -->
    <div class="right-side">
      <div class="small-card">
        <h4>Account Settings</h4>
        <ul style="list-style:none; padding-left:0; margin-top:12px;">
          <li style="padding:8px 0;">⚙ Preferences</li>
          <li style="padding:8px 0;">💳 Payment Methods</li>
          <li style="padding:8px 0;">🔔 Notifications</li>
        </ul>
      </div>

      <div class="membership-card">
        <div>Status</div>
        <h2>Gold Member</h2>
        <p>Reward Points: <?= htmlspecialchars($user['reward_points']) ?></p>
        <button class="benefits-btn">View Benefits</button>
      </div>
    </div>
  </div>

  <script>
    // Clicking the label opens the file chooser
    document.querySelector('.upload-label').addEventListener('click', function() {
      document.getElementById('profile_image').click();
    });
  </script>

</body>

</html>