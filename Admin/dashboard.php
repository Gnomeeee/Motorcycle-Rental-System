<?php
session_start();

if (!isset($_SESSION['user_id'])) {
  $_SESSION['user_id'] = 1;
  $_SESSION['role'] = 'admin';
}

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
  header("Location: ../login.php");
  exit();
}

require_once "../Database/dbconnect.php";

$admin_id = $_SESSION['user_id'];

// --- 1. Fetch admin info ---
$stmt = $conn->prepare("SELECT full_name, role, profile_image FROM users WHERE user_id = ? AND role = 'Admin'");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

$admin_name = $admin['full_name'] ?? 'Admin User';
$admin_role = $admin['role'] ?? 'Administrator';
$profile_img = $admin['profile_image'] ?? "https://ui-avatars.com/api/?name=Admin+User&background=random";

// --- 2. Dashboard stats ---
$total_revenue = $conn->query("SELECT SUM(total_cost) as total FROM reservations")->fetch_assoc()['total'] ?? 0;
$active_rentals = $conn->query("SELECT COUNT(*) as active FROM reservations WHERE status='Pending' OR status='Active'")->fetch_assoc()['active'] ?? 0;
$total_customers = $conn->query("SELECT COUNT(*) as customers FROM users WHERE role='Customer'")->fetch_assoc()['customers'] ?? 0;
$pending_approvals = $conn->query("SELECT COUNT(*) as pending FROM reservations WHERE status='Pending'")->fetch_assoc()['pending'] ?? 0;

// --- 3. Recent activity ---
$recent_activity = [];
$res = $conn->query("
    SELECT r.reservation_id, u.full_name as user, 'New reservation' as type, r.date_created as date
    FROM reservations r
    JOIN users u ON r.user_id = u.user_id
    ORDER BY r.date_created DESC LIMIT 5
");

if ($res && $res->num_rows > 0) {
  while ($row = $res->fetch_assoc()) {
    $recent_activity[] = $row;
  }
} else {
  // Dummy data
  $recent_activity = [
    ['user' => 'John Smith', 'type' => 'New reservation', 'date' => date('Y-m-d H:i:s', strtotime('-5 minutes'))],
    ['user' => 'Sarah Johnson', 'type' => 'Payment received', 'date' => date('Y-m-d H:i:s', strtotime('-15 minutes'))],
    ['user' => 'Mike Davis', 'type' => 'Bike returned', 'date' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
  ];
}

// --- 4. Top performing motorcycles ---
$top_favorites = [];
$sql_favs = "
    SELECT 
        m.model, 
        m.rate_per_hour,
        COUNT(f.favorite_id) as favorites
    FROM favorites f
    JOIN motorcycles m ON f.motorcycle_id = m.motorcycle_id
    GROUP BY f.motorcycle_id, m.model, m.rate_per_hour
    ORDER BY favorites DESC
    LIMIT 3
";
$res = $conn->query($sql_favs);

if ($res && $res->num_rows > 0) {
  while ($row = $res->fetch_assoc()) {
    $row['price_per_day'] = $row['rate_per_hour'] * 24; // convert hourly rate to daily estimate
    $top_favorites[] = $row;
  }
}

// --- 5. Revenue data for last 6 months ---
$revenue_labels = [];
$revenue_data = [];
for ($i = 5; $i >= 0; $i--) {
  $month = date('Y-m', strtotime("-$i month"));
  $month_label = date('M', strtotime($month . '-01'));
  $total = $conn->query("
        SELECT SUM(total_cost) as revenue 
        FROM reservations 
        WHERE DATE_FORMAT(date_created, '%Y-%m') = '$month'
    ")->fetch_assoc()['revenue'] ?? 0;

  $revenue_labels[] = $month_label;
  $revenue_data[] = (float)$total;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard — MotoRide</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="./Styles/dashboard.css">
</head>

<body>
  <header class="topbar">
    <div class="brand">
      <div class="brand-icon"><i class="fa-solid fa-motorcycle text-white"></i></div>
      <div class="brand-text">
        <h1>MotoRide Admin</h1><span>Management Portal</span>
      </div>
    </div>
    <div class="user-menu">
      <div class="notif-icon"><i class="fa-regular fa-bell"></i>
        <div class="notif-dot"></div>
      </div>
      <div class="profile" onclick="location.href='../profile.php'">
        <img src="<?= htmlspecialchars($profile_img) ?>" alt="Admin">
        <div class="profile-info">
          <span class="name"><?= htmlspecialchars($admin_name) ?></span>
          <span class="role"><?= htmlspecialchars($admin_role) ?></span>
        </div>
        <a href="logout.php" style="color:#9ca3af; margin-left:8px;" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></a>
      </div>
    </div>
  </header>

  <div class="container">
    <div class="nav-tabs">
      <a href="dashboard.php" class="nav-btn active"><i class="fa-solid fa-chart-pie"></i> Overview</a>
      <a href="motorcycle.php" class="nav-btn"><i class="fa-solid fa-motorcycle"></i> Motorcycles</a>
      <a href="reservations.php" class="nav-btn"><i class="fa-regular fa-calendar-check"></i> Reservations</a>
      <a href="customer.php" class="nav-btn"><i class="fa-solid fa-users"></i> Customers</a>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-header">
          <div class="icon-box" style="background: var(--green-bg); color: var(--green-text);"><i class="fa-solid fa-peso-sign"></i></div>
          <div class="badge up">+12.5%</div>
        </div>
        <div>
          <div class="stat-title">Total Revenue</div>
          <div class="stat-value">₱<?= number_format($total_revenue) ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div class="icon-box" style="background: var(--blue-bg); color: var(--blue-text);"><i class="fa-solid fa-motorcycle"></i></div>
          <div class="badge up">+3</div>
        </div>
        <div>
          <div class="stat-title">Active Rentals</div>
          <div class="stat-value"><?= $active_rentals ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div class="icon-box" style="background: var(--purple-bg); color: var(--purple-text);"><i class="fa-solid fa-users"></i></div>
          <div class="badge up">+15</div>
        </div>
        <div>
          <div class="stat-title">Total Customers</div>
          <div class="stat-value"><?= $total_customers ?></div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <div class="icon-box" style="background: var(--orange-bg); color: var(--orange-text);"><i class="fa-regular fa-clock"></i></div>
          <div class="badge down">-2</div>
        </div>
        <div>
          <div class="stat-title">Pending Approvals</div>
          <div class="stat-value"><?= $pending_approvals ?></div>
        </div>
      </div>
    </div>

    <div class="content-split">
      <div class="main-left">
        <div class="section-card" style="margin-bottom: 24px;">
          <div class="section-header">
            <h3>Revenue Overview</h3>
            <p>Monthly revenue for the past 6 months</p>
          </div>
          <div class="chart-container"><canvas id="revenueChart"></canvas></div>
        </div>

        <div class="section-card">
          <div class="section-header">
            <h3>Top Performing Motorcycles</h3>
            <p>Best performing bikes by user favorites</p>
          </div>
          <div class="top-bikes-container">
            <?php
            $rank = 1;
            $max_fav = !empty($top_favorites) ? max(array_column($top_favorites, 'favorites')) : 1;
            if ($max_fav == 0) $max_fav = 1;

            foreach ($top_favorites as $bike):
              $percentage = ($bike['favorites'] / $max_fav) * 100;
              $estimated_val = $bike['favorites'] * ($bike['rate_per_hour'] ?? 0);
            ?>
              <div class="bike-item">
                <div class="bike-header">
                  <div>
                    <span class="bike-rank">#<?= $rank++ ?></span>
                    <span><?= htmlspecialchars($bike['model']) ?></span>
                  </div>
                  <div class="bike-price">₱<?= number_format($estimated_val) ?> <span style="font-weight:400; color:#9ca3af; font-size:11px;">est.</span></div>
                </div>
                <div class="progress-bg">
                  <div class="progress-fill" style="width: <?= $percentage ?>%;"></div>
                </div>
              </div>
            <?php endforeach; ?>

            <?php if (empty($top_favorites)): ?>
              <p style="text-align:center; color:#9ca3af; font-size:13px; margin-top:20px;">No favorite data available yet.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="main-right">
        <div class="section-card">
          <div class="section-header">
            <h3>Recent Activity</h3>
            <p>Latest system activities</p>
          </div>
          <ul class="activity-list">
            <?php foreach ($recent_activity as $act):
              $iconClass = 'fa-regular fa-calendar';
              $colorClass = 'act-reservation';
              if (stripos($act['type'], 'Payment') !== false) {
                $iconClass = 'fa-solid fa-dollar-sign';
                $colorClass = 'act-payment';
              } elseif (stripos($act['type'], 'Bike') !== false) {
                $iconClass = 'fa-solid fa-motorcycle';
                $colorClass = 'act-bike';
              } elseif (stripos($act['type'], 'customer') !== false) {
                $iconClass = 'fa-regular fa-user';
                $colorClass = 'act-user';
              }
            ?>
              <li class="activity-item">
                <div class="activity-icon <?= $colorClass ?>"><i class="<?= $iconClass ?>"></i></div>
                <div class="activity-details">
                  <span class="activity-title"><?= htmlspecialchars($act['type']) ?></span>
                  <span class="activity-user"><?= htmlspecialchars($act['user']) ?></span>
                </div>
                <div class="activity-time">
                  <?php
                  $time = strtotime($act['date']);
                  $diff = time() - $time;
                  if ($diff < 3600) echo floor($diff / 60) . " min ago";
                  elseif ($diff < 86400) echo floor($diff / 3600) . " hr ago";
                  else echo date('M d', $time);
                  ?>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const ctx = document.getElementById('revenueChart').getContext('2d');
    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(255, 106, 0, 0.2)');
    gradient.addColorStop(1, 'rgba(255, 106, 0, 0)');

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= json_encode($revenue_labels) ?>,
        datasets: [{
          label: 'Revenue',
          data: <?= json_encode($revenue_data) ?>,
          borderColor: '#ff6a00',
          backgroundColor: gradient,
          borderWidth: 2,
          pointBackgroundColor: '#ffffff',
          pointBorderColor: '#ff6a00',
          pointRadius: 4,
          fill: true,
          tension: 0.4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: false
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              borderDash: [5, 5],
              drawBorder: false
            },
            ticks: {
              display: false
            }
          },
          x: {
            grid: {
              display: false
            },
            ticks: {
              color: '#9ca3af',
              font: {
                size: 11
              }
            }
          }
        }
      }
    });

    document.querySelectorAll('.nav-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
      });
    });
  </script>
</body>

</html>