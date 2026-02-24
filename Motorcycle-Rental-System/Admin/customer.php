<?php
session_start();
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
  header("Location: ../login.php");
  exit();
}

require_once "../Database/dbconnect.php";

$admin_id = $_SESSION['user_id'];

// Fetch admin info
$stmt = $conn->prepare("SELECT full_name, role, profile_image FROM users WHERE user_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$admin_name = $admin['full_name'] ?? 'Admin User';
$admin_role = $admin['role'] ?? 'Administrator';
$profile_img = $admin['profile_image'] ?? "https://ui-avatars.com/api/?name=Admin+User&background=random";


// Fetch customer list
$customers = [];
$sql = "
    SELECT 
        u.user_id, 
        u.full_name, 
        u.email, 
        u.contact_number, 
        u.reward_points,
        u.address,
        COUNT(r.reservation_id) AS total_rentals,
        SUM(r.total_cost) AS total_spent
    FROM users u
    LEFT JOIN reservations r ON u.user_id = r.user_id
    WHERE u.role = 'customer'
    GROUP BY u.user_id
    ORDER BY u.full_name ASC
";
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
  while ($row = $res->fetch_assoc()) {
    $customers[] = $row;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Customers — MotoRide Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      font-family: 'Inter', sans-serif;
      margin: 0;
      background: #f3f4f6;
      color: #111827;
    }

    .topbar {
      background: #111827;
      color: white;
      padding: 0 24px;
      height: 64px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .brand-icon {
      background: #ff6a00;
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
    }

    .brand-text h1 {
      margin: 0;
      font-size: 16px;
      font-weight: 600;
    }

    .brand-text span {
      font-size: 11px;
      color: #9ca3af;
    }

    .user-menu {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .profile {
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
    }

    .profile img {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      border: 2px solid #374151;
    }

    .profile-info .name {
      font-size: 13px;
      font-weight: 500;
      display: block;
    }

    .profile-info .role {
      font-size: 11px;
      color: #9ca3af;
    }

    .container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 24px;
    }

    .nav-tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 24px;
      overflow-x: auto;
      padding-bottom: 4px;
    }

    .nav-btn {
      background: white;
      border: 1px solid #e5e7eb;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 500;
      color: #6b7280;
      display: flex;
      align-items: center;
      gap: 6px;
      text-decoration: none;
    }

    .nav-btn.active {
      background: #f3f4f6;
      border-color: #d1d5db;
      color: #111827;
      font-weight: 600;
    }

    .toolbar-card {
      background: white;
      border-radius: 12px;
      padding: 18px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border: 1px solid #e5e7eb;
      margin-bottom: 24px;
    }

    .toolbar-left {
      display: flex;
      align-items: center;
      gap: 14px;
      flex: 1;
    }

    .search-input {
      position: relative;
      width: 100%;
      max-width: 350px;
    }

    .search-input input {
      width: 100%;
      padding: 10px 15px 10px 40px;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      background: #f9fafb;
      font-size: 14px;
    }

    .search-input i {
      position: absolute;
      left: 13px;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
    }

    .filter-btn,
    .add-btn {
      border-radius: 10px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      cursor: pointer;
    }

    .filter-btn {
      background: white;
      border: 1px solid #e5e7eb;
      color: #6b7280;
    }

    .filter-btn:hover {
      background: #f3f4f6;
    }

    .add-btn {
      background: #ff6a00;
      color: white;
      border: none;
      font-weight: 600;
    }

    .add-btn:hover {
      background: #e55f00;
    }

    .data-card {
      background: white;
      border-radius: 12px;
      border: 1px solid #e5e7eb;
      overflow-x: auto;
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
    }

    .data-table th {
      background: #f3f4f6;
      padding: 15px 20px;
      text-align: left;
      color: #6b7280;
      font-size: 12px;
      text-transform: uppercase;
      border-bottom: 1px solid #e5e7eb;
    }

    .data-table td {
      padding: 15px 20px;
      border-bottom: 1px solid #f3f4f6;
    }

    .view-btn {
      background: #e5e7eb;
      padding: 6px 12px;
      border-radius: 6px;
      font-size: 12px;
      text-decoration: none;
      color: #111827;
    }

    .view-btn:hover {
      background: #d1d5db;
    }
  </style>
</head>

<body>

  <header class="topbar">
    <div class="brand">
      <div class="brand-icon"><i class="fa-solid fa-motorcycle"></i></div>
      <div class="brand-text">
        <h1>MotoRide Admin</h1>
        <span>Management Portal</span>
      </div>
    </div>
    <div class="user-menu">
      <div class="profile">
        <img src="<?= htmlspecialchars($profile_img) ?>">
        <div class="profile-info">
          <span class="name"><?= htmlspecialchars($admin_name) ?></span>
          <span class="role"><?= htmlspecialchars($admin_role) ?></span>
        </div>
      </div>
      <a href="logout.php" style="color:#9ca3af;"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
  </header>

  <div class="container">

    <div class="nav-tabs">
      <a href="dashboard.php" class="nav-btn"><i class="fa-solid fa-chart-pie"></i> Overview</a>
      <a href="motorcycle.php" class="nav-btn"><i class="fa-solid fa-motorcycle"></i> Motorcycles</a>
      <a href="reservations.php" class="nav-btn"><i class="fa-regular fa-calendar-check"></i> Reservations</a>
      <a href="#" class="nav-btn active"><i class="fa-solid fa-users"></i> Customers</a>
    </div>

    <div class="toolbar-card">
      <div class="toolbar-left">
        <div class="search-input">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" placeholder="Search customers...">
        </div>
      </div>
      <div class="toolbar-right">
        <button class="filter-btn"><i class="fa-solid fa-filter"></i> Filter</button>
        <button class="add-btn" onclick="location.href='export_customers.php'"><i class="fa-solid fa-file-export"></i> Export</button>
      </div>
    </div>

    <div class="data-card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Contact</th>
            <th>Address</th>
            <th>Reward Points</th>
            <th>Total Rentals</th>
            <th>Total Spent</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['full_name']) ?></td>
              <td><?= htmlspecialchars($c['email']) ?></td>
              <td><?= htmlspecialchars($c['contact_number']) ?></td>
              <td><?= htmlspecialchars($c['address']) ?></td>
              <td><?= htmlspecialchars($c['reward_points']) ?></td>
              <td><?= $c['total_rentals'] ?? 0 ?></td>
              <td>₱<?= number_format($c['total_spent'] ?? 0, 2) ?></td>
              <td>
                <a class="view-btn" href="view-customer.php?id=<?= $c['user_id'] ?>"><i class="fa-solid fa-eye"></i> View</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>

</body>

</html>