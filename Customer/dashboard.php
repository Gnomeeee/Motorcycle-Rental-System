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
/* =============================================
   BOOKING MODAL — Modern Rental UX
   ============================================= */

/* Overlay */
#bookNowModal {
  display: none;
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  justify-content: center;
  align-items: center;
  z-index: 50;
  padding: 16px;
}

/* Modal card */
#bookNowModal .modal-card {
  background: #fff;
  width: 100%;
  max-width: 420px;
  border-radius: 24px;
  padding: 28px 24px 24px;
  box-shadow: 0 12px 40px rgba(0,0,0,0.18);
  position: relative;
  animation: slideUp 0.3s cubic-bezier(0.34,1.56,0.64,1);
  max-height: 90vh;
  overflow-y: auto;
}

@keyframes slideUp {
  from { transform: translateY(40px); opacity: 0; }
  to   { transform: translateY(0);    opacity: 1; }
}

/* Close button */
.modal-close {
  position: absolute;
  top: 14px;
  right: 14px;
  background: #f5f5f5;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  font-size: 16px;
  cursor: pointer;
  color: #555;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.2s, color 0.2s;
}
.modal-close:hover { background: #e0e0e0; color: #111; }

/* Bike image */
#modalBikeImage {
  width: 100%;
  height: 150px;
  object-fit: cover;
  border-radius: 14px;
  margin-bottom: 14px;
}

/* Bike info header */
.modal-bike-info { text-align: center; margin-bottom: 18px; }
.modal-bike-info h2 { font-size: 20px; font-weight: 700; color: #1a1a1a; margin: 0 0 4px; }
.modal-bike-info .plate { font-size: 13px; color: #888; margin: 0 0 6px; }
.modal-bike-info .rate { font-size: 20px; font-weight: 700; color: #2c7a7b; margin: 0; }

/* Divider */
.modal-divider { border: none; border-top: 1px solid #f0f0f0; margin: 0 0 16px; }

/* Form label */
.form-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #555;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}

/* Text input (full name) */
.modal-input {
  width: 100%;
  padding: 11px 14px;
  border-radius: 10px;
  border: 1.5px solid #e5e5e5;
  font-size: 14px;
  color: #1a1a1a;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
  margin-bottom: 14px;
  background: #fafafa;
}
.modal-input:focus {
  outline: none;
  border-color: #38b2ac;
  box-shadow: 0 0 0 3px rgba(56,178,172,0.12);
  background: #fff;
}

/* Date input */
.date-input-wrap { margin-bottom: 14px; }
.date-input-wrap input[type="date"] {
  width: 100%;
  padding: 11px 14px;
  border-radius: 10px;
  border: 1.5px solid #e5e5e5;
  font-size: 14px;
  color: #1a1a1a;
  box-sizing: border-box;
  background: #fafafa;
  transition: border-color 0.2s, box-shadow 0.2s;
  cursor: pointer;
}
.date-input-wrap input[type="date"]:focus {
  outline: none;
  border-color: #38b2ac;
  box-shadow: 0 0 0 3px rgba(56,178,172,0.12);
  background: #fff;
}

/* Time slot row */
.time-slot-row {
  display: flex;
  gap: 10px;
  margin-bottom: 14px;
}
.time-slot-row > div { flex: 1; }

/* Select dropdown */
.time-select {
  width: 100%;
  padding: 11px 12px;
  border-radius: 10px;
  border: 1.5px solid #e5e5e5;
  font-size: 14px;
  color: #1a1a1a;
  background: #fafafa;
  cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
  box-sizing: border-box;
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23888' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 12px center;
  padding-right: 30px;
}
.time-select:focus {
  outline: none;
  border-color: #38b2ac;
  box-shadow: 0 0 0 3px rgba(56,178,172,0.12);
  background-color: #fff;
}
.time-select:disabled { opacity: 0.5; cursor: not-allowed; }

/* Info note below time row */
.time-note {
  font-size: 12px;
  color: #aaa;
  text-align: center;
  margin: -8px 0 12px;
}

/* Duration + cost summary card */
.booking-summary {
  background: #f0fafa;
  border: 1.5px solid #b2dfdb;
  border-radius: 12px;
  padding: 12px 16px;
  margin-bottom: 16px;
  display: none; /* shown via JS when times are selected */
}
.booking-summary.visible { display: block; }
.summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 14px;
  color: #444;
}
.summary-row + .summary-row { margin-top: 6px; }
.summary-row .val { font-weight: 600; color: #1a1a1a; }
.summary-total {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px dashed #b2dfdb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.summary-total .label-total { font-size: 14px; font-weight: 600; color: #333; }
.summary-total .val-total { font-size: 22px; font-weight: 800; color: #2c7a7b; }

/* Error / warning message */
.booking-error {
  background: #fff5f5;
  border: 1px solid #fc8181;
  border-radius: 10px;
  color: #c53030;
  font-size: 13px;
  padding: 10px 14px;
  margin-bottom: 12px;
  display: none;
}
.booking-error.visible { display: block; }

/* Confirm button */
.confirm-btn {
  width: 100%;
  padding: 15px;
  background: linear-gradient(135deg, #38b2ac, #2c7a7b);
  color: #fff;
  border-radius: 12px;
  border: none;
  font-size: 15px;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.2s, transform 0.1s;
  letter-spacing: 0.3px;
}
.confirm-btn:hover   { opacity: 0.92; }
.confirm-btn:active  { transform: scale(0.98); }
.confirm-btn:disabled { background: #ccc; cursor: not-allowed; opacity: 1; }

/* Loading state */
.confirm-btn.loading::after {
  content: ' ⏳';
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
          <div class="value" id="favoritesCount"><?= (int)$favorites_count ?></div>
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

    <!-- Motorcycle Grid -->
    <div class="grid" id="grid">
      <?php foreach ($motorcycles as $m):
        $mid = (int)$m['motorcycle_id'];
        $model = htmlspecialchars($m['model']);
        $plate = htmlspecialchars($m['plate_number']);
        $price = number_format((float)$m['rate_per_hour'], 2);

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
                  <?= (float)$m['rate_per_hour'] ?>,
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


  <!-- =============================================
       BOOKING MODAL
       ============================================= -->
  <div id="bookNowModal">
    <div class="modal-card">

      <button class="modal-close" onclick="closeBookingModal()" title="Close">✕</button>

      <img id="modalBikeImage" src="" alt="Bike">

      <div class="modal-bike-info">
        <h2 id="modalBikeModel"></h2>
        <p class="plate" id="modalBikePlate"></p>
        <p class="rate" id="modalBikeHourlyRate"></p>
      </div>

      <hr class="modal-divider">

      <form id="modalBookingForm" autocomplete="off">
        <!-- Hidden fields submitted to booking.php -->
        <input type="hidden" id="modalBikeId"           name="motorcycle_id">
        <input type="hidden" id="calculatedTotalCost"   name="total_cost">
        <input type="hidden" id="hiddenStartDatetime"   name="start_date">
        <input type="hidden" id="hiddenEndDatetime"     name="end_date">

        <!-- Renter name -->
        <label class="form-label" for="renterName">Renter's Full Name</label>
        <input class="modal-input" id="renterName" type="text" name="full_name"
               placeholder="e.g. Juan Dela Cruz" required>

        <!-- Booking date -->
        <label class="form-label">Booking Date</label>
        <div class="date-input-wrap">
          <input type="date" id="bookingDate" name="booking_date"
                 onchange="onDateChange()" required>
        </div>

        <!-- Start & End time dropdowns -->
        <div class="time-slot-row">
          <div>
            <label class="form-label" for="startTimeSelect">Start Time</label>
            <select class="time-select" id="startTimeSelect"
                    onchange="onStartTimeChange()" required>
              <option value="">-- Pick --</option>
            </select>
          </div>
          <div>
            <label class="form-label" for="endTimeSelect">End Time</label>
            <select class="time-select" id="endTimeSelect"
                    onchange="onEndTimeChange()" required disabled>
              <option value="">-- Pick start first --</option>
            </select>
          </div>
        </div>
        <p class="time-note">Minimum rental: 1 hour • slots every 30 minutes</p>

        <!-- Error message -->
        <div class="booking-error" id="bookingError"></div>

        <!-- Summary card (shown once both times are selected) -->
        <div class="booking-summary" id="bookingSummary">
          <div class="summary-row">
            <span>Duration</span>
            <span class="val" id="summaryDuration">—</span>
          </div>
          <div class="summary-row">
            <span>Rate</span>
            <span class="val" id="summaryRate">—</span>
          </div>
          <div class="summary-total">
            <span class="label-total">Total Cost</span>
            <span class="val-total" id="summaryTotal">₱0.00</span>
          </div>
        </div>

        <button class="confirm-btn" type="submit" id="confirmBtn" disabled>
          Confirm Booking
        </button>

      </form>
    </div>
  </div>


  <script>
  /* =================================================
     FILTERS & SEARCH
  ================================================= */
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
      const matchCat = activeCat === 'all' || card.dataset.cat === activeCat;
      const matchSearch = card.dataset.name.includes(q);
      card.style.display = (matchCat && matchSearch) ? 'flex' : 'none';
    });
  }


  /* =================================================
     FAVORITE TOGGLE
  ================================================= */
  const favCounter = document.getElementById("favoritesCount");
  document.querySelectorAll('.fav').forEach(btn => {
    btn.addEventListener('click', function () {
      const bikeId = this.dataset.bike;
      const was = this.classList.contains('hearted');
      this.classList.toggle('hearted', !was);
      this.innerText = !was ? '❤' : '♡';

      fetch('../Authentication/favorite-toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ bike_id: bikeId })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          favCounter.innerText = parseInt(favCounter.innerText) + (data.action === 'added' ? 1 : -1);
        } else {
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


  /* =================================================
     BOOKING MODAL — Time Slot Logic
  ================================================= */
  let currentHourlyRate = 0;

  /**
   * Build array of 30-minute time slots for a full 24-hour day.
   * Returns: [ { value: "HH:MM", label: "H:MM AM/PM" }, ... ]
   */
  function buildAllSlots() {
    const slots = [];
    for (let h = 0; h < 24; h++) {
      for (let m = 0; m < 60; m += 30) {
        slots.push({
          value: `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}`,
          label: formatTime12(h, m)
        });
      }
    }
    return slots;
  }

  /** Convert 24-h integers to "3:30 PM" style */
  function formatTime12(h, m) {
    const ampm = h >= 12 ? 'PM' : 'AM';
    const hour = h % 12 || 12;
    return `${hour}:${String(m).padStart(2,'0')} ${ampm}`;
  }

  /** Return today's date string YYYY-MM-DD */
  function todayStr() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  }

  /**
   * Current time in total minutes (e.g. 14:35 → 875),
   * rounded UP to the nearest 30-min slot.
   */
  function currentMinutesRoundedUp() {
    const now = new Date();
    const raw = now.getHours() * 60 + now.getMinutes();
    return Math.ceil(raw / 30) * 30; // e.g. 14:10 → 870 (14:30)
  }

  /** Slot value "HH:MM" to total minutes */
  function slotToMinutes(value) {
    const [h, m] = value.split(':').map(Number);
    return h * 60 + m;
  }

  /** Populate the Start Time dropdown based on selected date */
  function populateStartSlots() {
    const selectedDate = document.getElementById('bookingDate').value;
    const isToday      = selectedDate === todayStr();
    const minMinutes   = isToday ? currentMinutesRoundedUp() : 0;

    const allSlots  = buildAllSlots();
    const startSel  = document.getElementById('startTimeSelect');
    startSel.innerHTML = '';

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '-- Select start time --';
    startSel.appendChild(placeholder);

    let firstFutureVal = null;
    allSlots.forEach(slot => {
      if (slotToMinutes(slot.value) < minMinutes) return; // hide past slots
      const opt = document.createElement('option');
      opt.value       = slot.value;
      opt.textContent = slot.label;
      startSel.appendChild(opt);
      if (!firstFutureVal) firstFutureVal = slot.value;
    });

    // Auto-select the nearest upcoming slot
    if (firstFutureVal) startSel.value = firstFutureVal;

    // Reset and rebuild end slots
    resetEndSlots();
    if (firstFutureVal) populateEndSlots(firstFutureVal);
  }

  /** Clear end time dropdown */
  function resetEndSlots() {
    const endSel = document.getElementById('endTimeSelect');
    endSel.innerHTML = '<option value="">-- Pick start first --</option>';
    endSel.disabled  = true;
    hideSummary();
    disableConfirm();
  }

  /**
   * Populate End Time dropdown.
   * Rules: minimum 1 hour after start; only same-day slots shown
   * (cross-midnight not allowed for simplicity — adjust if needed).
   */
  function populateEndSlots(startValue) {
    const startMin = slotToMinutes(startValue);
    const minEnd   = startMin + 60;     // at least 1 hour later
    const allSlots = buildAllSlots();

    const endSel = document.getElementById('endTimeSelect');
    endSel.innerHTML = '';

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '-- Select end time --';
    endSel.appendChild(placeholder);

    let count = 0;
    allSlots.forEach(slot => {
      const slotMin = slotToMinutes(slot.value);
      if (slotMin < minEnd) return; // must be at least 1 hr after start

      const opt = document.createElement('option');
      opt.value       = slot.value;
      // Show duration hint next to each option
      const diffH = Math.floor((slotMin - startMin) / 60);
      const diffM = (slotMin - startMin) % 60;
      const durLabel = diffM === 0 ? `${diffH}h` : `${diffH}h ${diffM}m`;
      opt.textContent = `${slot.label}  (${durLabel})`;
      endSel.appendChild(opt);
      count++;
    });

    if (count === 0) {
      // Edge case: start time is so late (e.g. 11:00 PM) no end slot fits today
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'No slots available today';
      endSel.appendChild(opt);
      endSel.disabled = true;
      showError("Start time is too late for a minimum 1-hour rental today. Please choose an earlier start time or a different date.");
      return;
    }

    endSel.disabled = false;
    endSel.value    = '';   // force user to pick explicitly
    hideError();
  }

  /* --- Event handlers --- */

  function onDateChange() {
    populateStartSlots();
    hideSummary();
    disableConfirm();
    hideError();
  }

  function onStartTimeChange() {
    const startVal = document.getElementById('startTimeSelect').value;
    if (!startVal) { resetEndSlots(); return; }
    populateEndSlots(startVal);
    hideSummary();
    disableConfirm();
    hideError();
  }

  function onEndTimeChange() {
    const startVal = document.getElementById('startTimeSelect').value;
    const endVal   = document.getElementById('endTimeSelect').value;
    if (!startVal || !endVal) { hideSummary(); disableConfirm(); return; }

    const startMin = slotToMinutes(startVal);
    const endMin   = slotToMinutes(endVal);

    if (endMin <= startMin) {
      showError("End time must be after start time.");
      hideSummary(); disableConfirm(); return;
    }

    hideError();
    updateSummary(startVal, endVal);
    enableConfirm();
  }

  /* --- Summary card --- */

  function updateSummary(startVal, endVal) {
    const startMin = slotToMinutes(startVal);
    const endMin   = slotToMinutes(endVal);
    const diffMin  = endMin - startMin;
    const hours    = diffMin / 60;
    const total    = (hours * currentHourlyRate).toFixed(2);

    const hPart = Math.floor(hours);
    const mPart = diffMin % 60;
    const durStr = hPart > 0
      ? (mPart > 0 ? `${hPart} hr ${mPart} min` : `${hPart} hr${hPart > 1 ? 's' : ''}`)
      : `${mPart} min`;

    document.getElementById('summaryDuration').textContent = durStr;
    document.getElementById('summaryRate').textContent     = `₱${currentHourlyRate.toFixed(2)}/hr`;
    document.getElementById('summaryTotal').textContent    = `₱${total}`;
    document.getElementById('calculatedTotalCost').value   = total;

    document.getElementById('bookingSummary').classList.add('visible');
  }

  function hideSummary() {
    document.getElementById('bookingSummary').classList.remove('visible');
    document.getElementById('calculatedTotalCost').value = '';
  }

  /* --- Error helpers --- */
  function showError(msg) {
    const el = document.getElementById('bookingError');
    el.textContent = msg;
    el.classList.add('visible');
  }
  function hideError() {
    document.getElementById('bookingError').classList.remove('visible');
  }

  /* --- Confirm button state --- */
  function enableConfirm()  { document.getElementById('confirmBtn').disabled = false; }
  function disableConfirm() { document.getElementById('confirmBtn').disabled = true;  }


  /* --- Open / Close modal --- */

  function openBookingModal(id, model, plate, hourlyRate, image) {
    currentHourlyRate = parseFloat(hourlyRate);

    document.getElementById('modalBikeId').value        = id;
    document.getElementById('modalBikeModel').innerText = model;
    document.getElementById('modalBikePlate').innerText = `Plate: ${plate}`;
    document.getElementById('modalBikeHourlyRate').innerText = `₱${currentHourlyRate.toFixed(2)} / hour`;
    document.getElementById('modalBikeImage').src       = image;

    // Reset form
    document.getElementById('modalBookingForm').reset();
    document.getElementById('modalBikeId').value = id; // restore after reset

    // Set date to today; restrict to today and future
    const dateInput = document.getElementById('bookingDate');
    dateInput.value = todayStr();
    dateInput.min   = todayStr();

    hideSummary();
    disableConfirm();
    hideError();

    // Populate start slots (auto-selects nearest future slot)
    populateStartSlots();

    // Show modal
    document.getElementById('bookNowModal').style.display = 'flex';
  }

  function closeBookingModal() {
    document.getElementById('bookNowModal').style.display = 'none';
  }

  // Close on backdrop click
  document.getElementById('bookNowModal').addEventListener('click', function(e) {
    if (e.target === this) closeBookingModal();
  });


  /* =================================================
     SUBMIT BOOKING
  ================================================= */
  document.getElementById('modalBookingForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const date     = document.getElementById('bookingDate').value;
    const startVal = document.getElementById('startTimeSelect').value;
    const endVal   = document.getElementById('endTimeSelect').value;
    const name     = document.getElementById('renterName').value.trim();

    if (!date || !startVal || !endVal || !name) {
      showError('Please fill in all fields before confirming.');
      return;
    }

    // Build full datetime strings (YYYY-MM-DD HH:MM:00)
    const startDatetime = `${date} ${startVal}:00`;
    const endDatetime   = `${date} ${endVal}:00`;

    // Populate hidden fields
    document.getElementById('hiddenStartDatetime').value = startDatetime;
    document.getElementById('hiddenEndDatetime').value   = endDatetime;

    // Disable button while submitting
    const btn = document.getElementById('confirmBtn');
    btn.disabled = true;
    btn.classList.add('loading');
    btn.textContent = 'Processing...';

    const formData = new FormData(this);
    // Ensure hidden datetime fields are included
    formData.set('start_date', startDatetime);
    formData.set('end_date',   endDatetime);

    fetch('booking.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        closeBookingModal();
        // Small success feedback before reload
        alert(`✅ Booking Confirmed!\n\nBike: ${document.getElementById('modalBikeModel').innerText}\nDate: ${date}\nTime: ${startVal} – ${endVal}\nTotal: ₱${document.getElementById('calculatedTotalCost').value}`);
        location.reload();
      } else {
        showError(data.message || 'Booking failed. Please try again.');
        btn.disabled  = false;
        btn.classList.remove('loading');
        btn.textContent = 'Confirm Booking';
      }
    })
    .catch(err => {
      console.error(err);
      showError('Network error. Please check your connection.');
      btn.disabled  = false;
      btn.classList.remove('loading');
      btn.textContent = 'Confirm Booking';
    });
  });
  </script>
</body>
</html>