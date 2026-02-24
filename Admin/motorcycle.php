<?php
session_start();
// --- Mocking session for preview purposes if not set (Remove this block in production) ---
if (!isset($_SESSION['user_id'])) {
  $_SESSION['user_id'] = 1;
  $_SESSION['role'] = 'admin';
}
// ---------------------------------------------------------------------------------------

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
  header("Location: ../login.php");
  exit();
}

require_once "../Database/dbconnect.php";

$admin_id = $_SESSION['user_id'];

// Fetch admin info
$stmt = $conn->prepare("SELECT full_name, role, profile_image FROM users WHERE user_id = ? AND role = 'Admin'");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$admin_name = $admin['full_name'] ?? 'Admin User';
$admin_role = $admin['role'] ?? 'Administrator';
$profile_img = $admin['profile_image'] ?? "https://ui-avatars.com/api/?name=Admin+User&background=random";

// Fetch motorcycles
$motorcycles = [];
$sql_bikes = "
    SELECT 
        m.motorcycle_id, 
        m.model, 
        m.category, 
        m.rate_per_hour,
        CASE 
            WHEN EXISTS (SELECT 1 FROM reservations r WHERE r.motorcycle_id = m.motorcycle_id AND r.status IN ('Active', 'Pending')) THEN 'Rented'
            WHEN m.status = 'Maintenance' THEN 'Maintenance' 
            ELSE 'Available' 
        END as status,
        COUNT(r.reservation_id) AS rentals,
        SUM(r.total_cost) AS revenue
    FROM motorcycles m
    LEFT JOIN reservations r 
    ON m.motorcycle_id = r.motorcycle_id AND r.status != 'Cancelled'
    GROUP BY m.motorcycle_id, m.model, m.category, m.rate_per_hour, m.status
    ORDER BY m.model ASC
";
$res_bikes = $conn->query($sql_bikes);

if ($res_bikes && $res_bikes->num_rows > 0) {
  while ($row = $res_bikes->fetch_assoc()) {
    $motorcycles[] = $row;
  }
} else {
  // Dummy fallback for UI preview
  $motorcycles = [
    ['motorcycle_id' => 1, 'model' => 'Yamaha YZF-R1', 'category' => 'Sport', 'status' => 'Available', 'rate_per_hour' => 120, 'rentals' => 45, 'revenue' => 5400],
    ['motorcycle_id' => 2, 'model' => 'BMW R 1250 GS', 'category' => 'Adventure', 'status' => 'Available', 'rate_per_hour' => 150, 'rentals' => 28, 'revenue' => 4300],
    ['motorcycle_id' => 3, 'model' => 'Harley Davidson Street', 'category' => 'Cruiser', 'status' => 'Rented', 'rate_per_hour' => 85, 'rentals' => 38, 'revenue' => 3230],
    ['motorcycle_id' => 4, 'model' => 'Kawasaki Ninja 650', 'category' => 'Sport', 'status' => 'Maintenance', 'rate_per_hour' => 85, 'rentals' => 32, 'revenue' => 2720],
  ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Motorcycles Management — MotoRide</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root {
      --primary: #ff6a00;
      --bg-body: #f3f4f6;
      --bg-card: #ffffff;
      --text-dark: #111827;
      --text-gray: #6b7280;
      --border: #e5e7eb;

      --green-bg: #dcfce7;
      --green-text: #16a34a;
      --blue-bg: #dbeafe;
      --blue-text: #2563eb;
      --orange-bg: #ffedd5;
      --orange-text: #ea580c;

      --available-bg: var(--green-bg);
      --available-text: var(--green-text);
      --rented-bg: var(--blue-bg);
      --rented-text: var(--blue-text);
      --maintenance-bg: var(--orange-bg);
      --maintenance-text: var(--orange-text);
    }

    body {
      font-family: 'Inter', sans-serif;
      margin: 0;
      background: var(--bg-body);
      color: var(--text-dark);
    }

    /* HEADER */
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
      background: var(--primary);
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
    }

    .brand-text h1 {
      font-size: 16px;
      font-weight: 600;
      margin: 0;
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

    /* NAV TABS */
    .nav-tabs {
      display: flex;
      gap: 8px;
      margin-bottom: 24px;
      overflow-x: auto;
      padding-bottom: 4px;
    }

    .nav-btn {
      background: #ffffff;
      border: 1px solid #e5e7eb;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 500;
      color: var(--text-gray);
      display: flex;
      align-items: center;
      gap: 6px;
      transition: 0.2s;
      text-decoration: none;
    }

    .nav-btn.active {
      background: #f3f4f6;
      border-color: #d1d5db;
      color: var(--text-dark);
      font-weight: 600;
    }

    /* 🔥 UPDATED TOOLBAR — NEW DESIGN */
    .toolbar-card {
      background: var(--bg-card);
      border-radius: 12px;
      padding: 18px 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border: 1px solid var(--border);
      margin-bottom: 24px;
    }

    .toolbar-left {
      display: flex;
      align-items: center;
      gap: 14px;
      flex: 1;
    }

    .toolbar-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .search-input {
      position: relative;
      width: 100%;
      max-width: 350px;
    }

    .search-input input {
      width: 100%;
      padding: 10px 15px 10px 40px;
      border: 1px solid var(--border);
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

    .filter-btn {
      background: white;
      border: 1px solid var(--border);
      padding: 10px 16px;
      border-radius: 10px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--text-gray);
      cursor: pointer;
      transition: 0.2s;
    }

    .filter-btn:hover {
      background: #f3f4f6;
    }

    .add-btn {
      background: var(--primary);
      color: white;
      padding: 10px 18px;
      border-radius: 10px;
      font-size: 14px;
      border: none;
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.2s;
    }

    .add-btn:hover {
      background: #e55f00;
    }

    /* TABLE */
    .data-card {
      background: white;
      border-radius: 12px;
      border: 1px solid var(--border);
      overflow-x: auto;
    }

    .data-table {
      width: 100%;
      border-collapse: collapse;
    }

    .data-table th {
      background: var(--bg-body);
      padding: 15px 20px;
      text-align: left;
      color: var(--text-gray);
      font-size: 12px;
      text-transform: uppercase;
      border-bottom: 1px solid var(--border);
    }

    .data-table td {
      padding: 15px 20px;
      border-bottom: 1px solid #f3f4f6;
    }

    .status-tag {
      padding: 4px 10px;
      border-radius: 16px;
      font-size: 12px;
      font-weight: 600;
    }

    .status-tag.Available {
      background: var(--available-bg);
      color: var(--available-text);
    }

    .status-tag.Rented {
      background: var(--rented-bg);
      color: var(--rented-text);
    }

    .status-tag.Maintenance {
      background: var(--maintenance-bg);
      color: var(--maintenance-text);
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

    <!-- NAV -->
    <div class="nav-tabs">
      <a href="dashboard.php" class="nav-btn"><i class="fa-solid fa-chart-pie"></i> Overview</a>
      <a href="motorcycle.php" class="nav-btn active"><i class="fa-solid fa-motorcycle"></i> Motorcycles</a>
      <a href="reservations.php" class="nav-btn"><i class="fa-regular fa-calendar-check"></i> Reservations</a>
      <a href="customer.php" class="nav-btn"><i class="fa-solid fa-users"></i> Customers</a>
    </div>

    <!-- 🔥 UPDATED TOOLBAR (Search LEFT, Filter + Add RIGHT) -->
    <div class="toolbar-card">

      <div class="toolbar-left">
        <div class="search-input">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" placeholder="Search motorcycles..." name="q">
        </div>
      </div>

      <div class="toolbar-right">
        <button class="filter-btn">
          <i class="fa-solid fa-filter"></i>
          Filter
        </button>

        <button class="add-btn" onclick="location.href='add_motorcycle.php'">
          <i class="fa-solid fa-plus"></i>
          Add Motorcycle
        </button>
      </div>

    </div>

    <!-- TABLE -->
    <div class="data-card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Motorcycle</th>
            <th>Category</th>
            <th>Status</th>
            <th>Price/Day</th>
            <th>Rentals</th>
            <th>Revenue</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($motorcycles as $bike): ?>
            <tr>
              <td><?= htmlspecialchars($bike['model']) ?></td>
              <td><?= htmlspecialchars($bike['category']) ?></td>
              <td><span class="status-tag <?= htmlspecialchars($bike['status']) ?>"><?= htmlspecialchars($bike['status']) ?></span></td>
              <td>₱<?= number_format($bike['rate_per_hour']) ?></td>
              <td><?= number_format($bike['rentals']) ?></td>
              <td>₱<?= number_format($bike['revenue']) ?></td>
              <td>
                <a href="edit_motorcycle.php?id=<?= $bike['motorcycle_id'] ?>"><i class="fa-solid fa-pen"></i></a>
                <a href="delete_motorcycle.php?id=<?= $bike['motorcycle_id'] ?>" onclick="return confirm('Delete this motorcycle?')" style="color:#ef4444;"><i class="fa-solid fa-trash"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>

      </table>
    </div>

  </div>

</body>

</html>