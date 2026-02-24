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

// Fetch reservations
$reservations = [];
$sql_res = "
    SELECT r.reservation_id, r.user_id, r.motorcycle_id, r.start_date, r.end_date, r.total_cost, r.status,
           u.full_name AS customer_name, m.model AS motorcycle_model
    FROM reservations r
    JOIN users u ON r.user_id = u.user_id
    JOIN motorcycles m ON r.motorcycle_id = m.motorcycle_id
    ORDER BY r.start_date DESC
";
$res_res = $conn->query($sql_res);
if ($res_res && $res_res->num_rows > 0) {
  while ($row = $res_res->fetch_assoc()) {
    $reservations[] = $row;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Admin Reservations — MotoRide</title>
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
      font-size: 16px;
      margin: 0;
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

    .status-tag {
      padding: 4px 10px;
      border-radius: 16px;
      font-size: 12px;
      font-weight: 600;
    }

    .status-tag.Pending {
      background: #fef3c7;
      color: #b45309;
    }

    .status-tag.Approved {
      background: #d1fae5;
      color: #065f46;
    }

    .status-tag.Rejected {
      background: #fee2e2;
      color: #991b1b;
    }

    .status-tag.Completed {
      background: #dbeafe;
      color: green;
    }

    .status-tag.Cancelled {
      background: #fee2e2;
      color: #991b1b;
    }

    .action-btn {
      padding: 5px 12px;
      border-radius: 6px;
      border: 1px solid;
      font-size: 12px;
      cursor: pointer;
      margin-right: 4px;
      text-decoration: none;
      color: gray;
    }

    .action-btn:hover {
      background-color: lightgray;
    }

    .approve-btn {
      background: #16a34a;
      color: white;
    }

    .reject-btn {
      background: #ef4444;
      color: white;
    }
  </style>
</head>

<body>

  <header class="topbar">
    <div class="brand">
      <div class="brand-icon"><i class="fa-solid fa-motorcycle"></i></div>
      <div class="brand-text">
        <h1>MotoRide Admin</h1><span>Management Portal</span>
      </div>
    </div>
    <div class="user-menu">
      <div class="profile">
        <img src="<?= htmlspecialchars($profile_img) ?>">
        <div class="profile-info"><span class="name"><?= htmlspecialchars($admin_name) ?></span><span class="role"><?= htmlspecialchars($admin_role) ?></span></div>
      </div>
      <a href="logout.php" style="color:#9ca3af;"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
  </header>

  <div class="container">

    <div class="nav-tabs">
      <a href="dashboard.php" class="nav-btn"><i class="fa-solid fa-chart-pie"></i> Overview</a>
      <a href="motorcycle.php" class="nav-btn"><i class="fa-solid fa-motorcycle"></i> Motorcycles</a>
      <a href="reservations.php" class="nav-btn active"><i class="fa-regular fa-calendar-check"></i> Reservations</a>
      <a href="customer.php" class="nav-btn"><i class="fa-solid fa-users"></i> Customers</a>
    </div>

    <div class="toolbar-card">
      <div class="toolbar-left">
        <div class="search-input">
          <i class="fa-solid fa-magnifying-glass"></i>
          <input type="text" placeholder="Search reservations..." name="q">
        </div>
      </div>
      <div class="toolbar-right">
        <button class="filter-btn"><i class="fa-solid fa-filter"></i> Filter</button>
        <button class="add-btn" onclick="location.href='export_reservations.php'"><i class="fa-solid fa-file-export"></i> Export</button>
      </div>
    </div>

    <div class="data-card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Customer</th>
            <th>Motorcycle</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Status</th>
            <th>Total</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reservations as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['customer_name']) ?></td>
              <td><?= htmlspecialchars($r['motorcycle_model']) ?></td>
              <td><?= date('Y-m-d', strtotime($r['start_date'])) ?></td>
              <td><?= date('Y-m-d', strtotime($r['end_date'])) ?></td>
              <td><span class="status-tag <?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
              <td>₱<?= number_format($r['total_cost'], 2) ?></td>
              <td>
                <?php if ($r['status'] === 'Pending'): ?>
                  <form style="display:inline;" method="post" action="update_reservation.php">
                    <input type="hidden" name="reservation_id" value="<?= $r['reservation_id'] ?>">
                    <button class="action-btn approve-btn" name="action" value="approve">Approve</button>
                    <button class="action-btn reject-btn" name="action" value="reject">Reject</button>
                  </form>

                <?php elseif ($r['status'] === 'Approved'): ?>
                  <form style="display:inline;" method="post" action="update_reservation.php">
                    <input type="hidden" name="reservation_id" value="<?= $r['reservation_id'] ?>">
                    <button class="action-btn completed-btn" name="action" value="completed">Completed</button>
                  </form>

                <?php else: ?>
                  <a class="action-btn" href="view_reservation.php?id=<?= $r['reservation_id'] ?>"><i class="fa-solid fa-eye"></i> View Details</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>
</body>

</html>