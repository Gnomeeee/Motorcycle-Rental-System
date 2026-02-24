<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

require_once "../Database/dbconnect.php";

$user_id = $_SESSION['user_id'];

// === Profile Image Handling ===
$upload_folder = "../Assets/Uploads/";
$default_avatar = "../Assets/Images/download.png";
$profile_img = $default_avatar;

$stmt = $conn->prepare("SELECT full_name, role, profile_image, reward_points FROM users WHERE user_id = ? LIMIT 1");
if ($stmt) {
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    $_SESSION['user_name'] = $row['full_name'];
    $_SESSION['role'] = $row['role'];
    $reward_points = $row['reward_points'] ?? 0;

    if (!empty($row['profile_image']) && file_exists($upload_folder . $row['profile_image'])) {
      $profile_img = $upload_folder . htmlspecialchars($row['profile_image']);
    }
  }
  $stmt->close();
}

// === Stats ===
$available_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM motorcycles WHERE status = 'available'");
$stmt->execute();
$res = $stmt->get_result();
$available_count = $res->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

$total_rides = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM reservations WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$total_rides = $res->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

$favorites_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM favorites WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$favorites_count = $res->fetch_assoc()['cnt'] ?? 0;
$stmt->close();

// === Motorcycles ===
$motorcycles = [];
$stmt = $conn->prepare("SELECT motorcycle_id, model, plate_number, rate_per_hour, status, image_url, category FROM motorcycles ORDER BY motorcycle_id DESC LIMIT 20");
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
  $motorcycles[] = $r;
}
$stmt->close();

// === Favorite IDs ===
$favorite_ids = [];
$stmt = $conn->prepare("SELECT motorcycle_id FROM favorites WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
  $favorite_ids[] = (int)$r['motorcycle_id'];
}
$stmt->close();

$current_page = basename($_SERVER['PHP_SELF']);
$placeholder = "../Assets/Images/download.png";
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <title>MotoRide — Customer Dashboard</title>
  <link rel="stylesheet" href="./Styles/dashboard.css">
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    /* Modal overlay */
    #bookNowModal {
      display: none;
      /* hidden by default */
      position: fixed;
      inset: 0;
      background-color: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(3px);
      justify-content: center;
      align-items: center;
      z-index: 50;
    }

    /* Modal box */
    #bookNowModal .animate-slide-up {
      background-color: #ffffff;
      width: 100%;
      max-width: 400px;
      border-radius: 20px;
      padding: 24px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      animation: slideUp 0.3s ease-out;
    }

    /* Slide up animation */
    @keyframes slideUp {
      from {
        transform: translateY(30px);
        opacity: 0;
      }

      to {
        transform: translateY(0);
        opacity: 1;
      }
    }

    /* Close button */
    #bookNowModal button[onclick^="closeBookingModal"] {
      position: absolute;
      top: 12px;
      right: 12px;
      background: none;
      border: none;
      font-size: 20px;
      cursor: pointer;
      color: #888;
      transition: color 0.2s;
    }

    #bookNowModal button[onclick^="closeBookingModal"]:hover {
      color: #444;
    }

    /* Modal content image */
    #bookNowModal img#modalBikeImage {
      width: 160px;
      height: 110px;
      object-fit: cover;
      border-radius: 12px;
      margin-bottom: 16px;
    }

    /* Bike info */
    #bookNowModal h2#modalBikeModel {
      font-size: 22px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 4px;
      text-align: center;
    }

    #bookNowModal p#modalBikePlate {
      font-size: 14px;
      color: #666;
      margin-bottom: 6px;
    }

    #bookNowModal p#modalBikePrice {
      font-size: 18px;
      font-weight: 700;
      color: #2c7a7b;
      /* green-ish */
      margin-bottom: 16px;
    }

    /* Booking form */
    #modalBookingForm input,
    #modalBookingForm select {
      width: 100%;
      padding: 15px 0;
      border-radius: 10px;
      border: 1px solid #ddd;
      margin-bottom: 10px;
      font-size: 14px;
      transition: all 0.2s;
    }

    #modalBookingForm input:focus,
    #modalBookingForm select:focus {
      outline: none;
      border-color: #38b2ac;
      /* highlight color */
      box-shadow: 0 0 0 2px rgba(56, 178, 172, 0.2);
    }


    /* Submit button */
    #modalBookingForm button[type="submit"] {
      width: 100%;
      padding: 15px;
      background-color: #38b2ac;
      color: #fff;
      border-radius: 12px;
      border: none;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s;
    }

    #modalBookingForm button[type="submit"]:hover {
      background-color: #2c7a7b;
    }
  </style>
</head>

<body>

  <!-- Topbar -->
  <div class="topbar">
    <div class="brand">
      <div class="logo">
        <img src="../Assets/Svg/motorcycle-svgrepo-com.svg" alt="">
      </div>
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
        <div class="name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
        <div class="role"><?= htmlspecialchars($_SESSION['role']) ?></div>
        <a href="logout.php" class="logout-btn">Logout</a>
      </div>
    </div>
  </div>

  <div class="container">

    <!-- Stats -->
    <div class="stats">
      <div class="stat">
        <div>
          <div class="label">Available Motorcycles</div>
          <div class="value"><?= (int)$available_count ?></div>
        </div>
        <div style="background:#e9fff5;border-radius:10px;padding:10px">🏍</div>
      </div>

      <div class="stat">
        <div>
          <div class="label">Total Rides</div>
          <div class="value"><?= (int)$total_rides ?></div>
        </div>
        <div style="background:#eef8ff;border-radius:10px;padding:10px">📈</div>
      </div>

      <div class="stat">
        <div>
          <div class="label">Favorites</div>
          <div class="value"><?= (int)$favorites_count ?></div>
        </div>
        <div style="background:#fff6ef;border-radius:10px;padding:10px">❤️</div>
      </div>

      <div class="stat">
        <div>
          <div class="label">Reward Points</div>
          <div class="value"><?= (int)$reward_points ?></div>
        </div>
        <div style="background:#f9f0ff;border-radius:10px;padding:10px">⭐</div>
      </div>
    </div>

    <!-- Search & Filter -->
    <div class="controls">
      <div class="search-row">

        <div class="search-box">
          <svg width="18" height="18" style="margin-right:8px" viewBox="0 0 24 24">
            <path fill="#999" d="M21 20l-5.6-5.6A7 7 0 1 0 17 17.4L22 22"></path>
          </svg>
          <input id="search" placeholder="Search motorcycles..." />
        </div>

        <div class="tag-wrap">
          <button class="chip active" data-cat="all">All Bikes</button>
          <button class="chip" data-cat="scooter">Scooter</button>
          <button class="chip" data-cat="underbone">Underbone</button>
          <button class="chip" data-cat="standard">Standard</button>
          <button class="chip" data-cat="automatic">Automatic</button>
          <button class="chip" data-cat="sport">Sport</button>
        </div>

      </div>
    </div>

    <!-- Motorcycle Grid (fixed position) -->
    <div class="grid" id="grid">
      <?php foreach ($motorcycles as $m):
        $mid = (int)$m['motorcycle_id'];
        $model = htmlspecialchars($m['model']);
        $plate = htmlspecialchars($m['plate_number']);
        $price = number_format((float)$m['rate_per_hour'], 2);

        // FIX image path
        $image_path = (!empty($m['image_url']) && file_exists("../" . $m['image_url']))
          ? "../" . $m['image_url']
          : $placeholder;

        $is_available = strtolower($m['status']) === 'available';

        $valid_categories = ['scooter', 'underbone', 'standard', 'automatic', 'sport'];
        $category = in_array(strtolower($m['category']), $valid_categories)
          ? strtolower($m['category'])
          : 'other';

        $isFav = in_array($mid, $favorite_ids);
      ?>
        <div class="card" data-cat="<?= $category ?>" data-name="<?= strtolower($model) ?>">
          <div class="media">
            <img src="<?= $image_path ?>" alt="<?= $model ?>" onerror="this.src='<?= $placeholder ?>'">

            <div class="fav <?= $isFav ? 'hearted' : '' ?>" data-bike="<?= $mid ?>">
              <?= $isFav ? '❤' : '♡' ?>
            </div>

            <?php if (!$is_available): ?>
              <div class="na-overlay">Not Available</div>
            <?php endif; ?>
          </div>

          <div class="body">
            <div class="title"><?= $model ?></div>
            <div class="meta">Plate: <?= $plate ?> • <?= ucfirst($category) ?></div>

            <div class="price-row">
              <div class="price">₱<?= $price ?>/hour</div>

              <?php if ($is_available): ?>
                <button class="book-btn" onclick='openBookingModal(
                <?= $mid ?>,
                <?= json_encode($model) ?>,
                <?= json_encode($plate) ?>,
                <?= json_encode($price) ?>,
                <?= json_encode($image_path) ?>
              )'>Book Now</button>
              <?php else: ?>
                <button class="book-btn disabled" disabled>Book Now</button>
              <?php endif; ?>
            </div>

          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <!-- Book Now Modal -->
  <div id="bookNowModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm hidden justify-center items-center z-50">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl p-6 relative animate-slide-up">

      <!-- Close Button -->
      <button onclick="closeBookingModal()"
        class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 text-xl font-bold">
        ✕
      </button>

      <!-- Modal Content -->
      <div class="flex flex-col items-center">
        <img id="modalBikeImage" src="" alt="Bike" class="w-40 h-28 object-cover rounded-lg mb-4">
        <h2 id="modalBikeModel" class="text-2xl font-semibold text-gray-800 mb-1"></h2>
        <p id="modalBikePlate" class="text-gray-500 mb-2"></p>
        <p id="modalBikePrice" class="text-green-600 font-bold mb-4"></p>

        <!-- Booking Form -->
        <form id="modalBookingForm" class="w-full space-y-3">
          <input type="hidden" id="modalBikeId" name="motorcycle_id">

          <input type="text" name="full_name" placeholder="Your Name" required
            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-green-400 focus:outline-none">

          <label>Start Date & Time</label>
          <input type="datetime-local" id="bookingStart" name="start_date" required
            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-green-400 focus:outline-none">

          <label>End Date & Time</label>
          <input type="datetime-local" id="bookingEnd" name="end_date" required
            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-green-400 focus:outline-none">

          <p id="totalCost" class="font-bold text-lg text-green-600">Total Cost: ₱0.00</p>

          <button type="submit"
            class="w-full bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 transition">
            Confirm Booking
          </button>
        </form>
      </div>
    </div>
  </div>


  <script>
    // ===== Filters & Search =====
    const chips = document.querySelectorAll('.chip');
    const searchInput = document.getElementById('search');
    let activeCat = 'all';

    chips.forEach(c => {
      c.addEventListener('click', () => {
        chips.forEach(x => x.classList.remove('active'));
        c.classList.add('active');
        activeCat = c.dataset.cat;
        applyFilters();
      });
    });

    searchInput.addEventListener('input', applyFilters);

    function applyFilters() {
      const q = searchInput.value.trim().toLowerCase();

      document.querySelectorAll('#grid .card').forEach(card => {
        const name = card.dataset.name;
        const cat = card.dataset.cat;

        const matchCat = (activeCat === 'all') || (cat === activeCat);
        const matchSearch = name.includes(q);

        card.style.display = (matchCat && matchSearch) ? 'flex' : 'none';
      });
    }

    // ===== Favorite Toggle =====
    document.querySelectorAll('.fav').forEach(btn => {
      btn.addEventListener('click', function() {
        const bikeId = this.dataset.bike;
        const was = this.classList.contains('hearted');

        this.classList.toggle('hearted', !was);
        this.innerText = !was ? '❤' : '♡';

        fetch('../Authentication/favorite-toggle.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              bike_id: bikeId
            })
          })
          .then(r => r.json())
          .then(data => {
            if (!data.success) {
              alert(data.message || 'Could not update favorite');
              this.classList.toggle('hearted', was);
              this.innerText = was ? '❤' : '♡';
            }
          })
          .catch(() => {
            alert('Network error');
            this.classList.toggle('hearted', was);
            this.innerText = was ? '❤' : '♡';
          });
      });
    });
    let currentRate = 0;

    // Open modal and set initial data
    function openBookingModal(id, model, plate, price, image) {
      const modal = document.getElementById('bookNowModal');
      modal.style.display = 'flex';
      document.getElementById('modalBikeId').value = id;
      document.getElementById('modalBikeModel').innerText = model;
      document.getElementById('modalBikePlate').innerText = "Plate: " + plate;
      document.getElementById('modalBikePrice').innerText = "₱" + price + "/hour";
      document.getElementById('modalBikeImage').src = image;

      currentRate = parseFloat(price); // store rate per hour
      document.getElementById('totalCost').innerText = "Total Cost: ₱0.00";

      // reset datetime fields
      document.getElementById('bookingStart').value = '';
      document.getElementById('bookingEnd').value = '';
    }

    // Close modal
    function closeBookingModal() {
      document.getElementById('bookNowModal').style.display = 'none';
    }

    // Update total cost dynamically
    function updateTotalCost() {
      const start = document.getElementById('bookingStart').value;
      const end = document.getElementById('bookingEnd').value;

      if (start && end) {
        const startTs = new Date(start).getTime();
        const endTs = new Date(end).getTime();

        if (endTs <= startTs) {
          document.getElementById('totalCost').innerText = "Invalid date range";
          return;
        }

        // Calculate duration in hours
        const hours = Math.ceil((endTs - startTs) / (1000 * 60 * 60));
        const total = hours * currentRate;

        document.getElementById('totalCost').innerText = `Total Cost: ₱${total.toFixed(2)}`;
      }
    }

    document.getElementById('bookingStart').addEventListener('change', updateTotalCost);
    document.getElementById('bookingEnd').addEventListener('change', updateTotalCost);

    // Submit booking via AJAX
    document.getElementById('modalBookingForm').addEventListener('submit', function(e) {
      e.preventDefault();

      const formData = new FormData(this);

      fetch('booking.php', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            alert(`Booking confirmed! Total cost: ₱${data.total_cost}`);
            closeBookingModal();
            location.reload();
          } else {
            alert(data.message || 'Booking failed!');
          }
        })
        .catch(err => {
          console.error(err);
          alert('Network error.');
        });
    });
  </script>

</body>

</html>