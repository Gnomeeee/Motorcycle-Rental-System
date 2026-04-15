<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once "../Database/dbconnect.php";

$user_id = $_SESSION['user_id'];

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

$motorcycles = [];
$stmt = $conn->prepare("SELECT motorcycle_id, model, plate_number, rate_per_hour, status, image_url, category FROM motorcycles ORDER BY motorcycle_id DESC LIMIT 20");
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    $motorcycles[] = $r;
}
$stmt->close();

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
        /* ── Booking Modal (unchanged) ── */
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

        #bookNowModal .modal-card {
            background: #fff;
            width: 100%;
            max-width: 420px;
            border-radius: 24px;
            padding: 28px 24px 24px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
            position: relative;
            animation: slideUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes slideUp {
            from {
                transform: translateY(40px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

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
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, color 0.2s;
        }

        .modal-close:hover {
            background: #e0e0e0;
            color: #111;
        }

        #modalBikeImage {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 14px;
            margin-bottom: 14px;
        }

        .modal-bike-info {
            text-align: center;
            margin-bottom: 18px;
        }

        .modal-bike-info h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 4px;
        }

        .modal-bike-info .plate {
            font-size: 13px;
            color: #888;
            margin: 0 0 6px;
        }

        .modal-bike-info .rate {
            font-size: 20px;
            font-weight: 700;
            color: #2c7a7b;
            margin: 0;
        }

        .modal-divider {
            border: none;
            border-top: 1px solid #f0f0f0;
            margin: 0 0 16px;
        }

        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

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
            box-shadow: 0 0 0 3px rgba(56, 178, 172, 0.12);
            background: #fff;
        }

        .date-input-wrap {
            margin-bottom: 14px;
        }

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
            box-shadow: 0 0 0 3px rgba(56, 178, 172, 0.12);
            background: #fff;
        }

        .time-slot-row {
            display: flex;
            gap: 10px;
            margin-bottom: 14px;
        }

        .time-slot-row>div {
            flex: 1;
        }

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
            box-shadow: 0 0 0 3px rgba(56, 178, 172, 0.12);
            background-color: #fff;
        }

        .time-select:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .time-note {
            font-size: 12px;
            color: #aaa;
            text-align: center;
            margin: -8px 0 12px;
        }

        .booking-summary {
            background: #f0fafa;
            border: 1.5px solid #b2dfdb;
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 16px;
            display: none;
        }

        .booking-summary.visible {
            display: block;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #444;
        }

        .summary-row+.summary-row {
            margin-top: 6px;
        }

        .summary-row .val {
            font-weight: 600;
            color: #1a1a1a;
        }

        .summary-total {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #b2dfdb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary-total .label-total {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .summary-total .val-total {
            font-size: 22px;
            font-weight: 800;
            color: #2c7a7b;
        }

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

        .booking-error.visible {
            display: block;
        }

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

        .confirm-btn:hover {
            opacity: 0.92;
        }

        .confirm-btn:active {
            transform: scale(0.98);
        }

        .confirm-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 1;
        }

        /* ════════════════════════════════
   GLOBAL NOTIFICATION DIALOG
   ════════════════════════════════ */
        .nd-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.48);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 16px;
        }

        .nd-overlay.show {
            display: flex;
            animation: ndFade .2s ease;
        }

        @keyframes ndFade {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .nd-card {
            background: #fff;
            border-radius: 22px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 28px 64px rgba(0, 0, 0, 0.22);
            overflow: hidden;
            animation: ndPop .32s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
        }

        @keyframes ndPop {
            from {
                transform: scale(0.86) translateY(24px);
                opacity: 0;
            }

            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        /* top accent stripe */
        .nd-stripe {
            height: 5px;
            width: 100%;
        }

        .nd-stripe.success {
            background: #16a34a;
        }

        .nd-stripe.error {
            background: #dc2626;
        }

        .nd-stripe.warning {
            background: #d97706;
        }

        .nd-body {
            padding: 28px 24px 22px;
            text-align: center;
        }

        /* icon circle */
        .nd-icon {
            width: 62px;
            height: 62px;
            border-radius: 50%;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nd-icon svg {
            width: 28px;
            height: 28px;
        }

        .nd-icon.success {
            background: #dcfce7;
            color: #16a34a;
        }

        .nd-icon.error {
            background: #fee2e2;
            color: #dc2626;
        }

        .nd-icon.warning {
            background: #fef3c7;
            color: #d97706;
        }

        .nd-title {
            font-size: 19px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 6px;
        }

        .nd-msg {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
            margin: 0;
        }

        /* ── Booking receipt strip (shown only on success) ── */
        .nd-receipt {
            background: #f8fafb;
            border-top: 1px solid #e5e7eb;
            border-bottom: 1px solid #e5e7eb;
            padding: 14px 24px;
            margin: 16px 0 0;
            text-align: left;
        }

        .nd-receipt-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #6b7280;
            padding: 4px 0;
        }

        .nd-receipt-row .rv {
            font-weight: 600;
            color: #111827;
        }

        .nd-receipt-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            margin-top: 6px;
            border-top: 1px dashed #d1d5db;
        }

        .nd-receipt-total .rl {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
        }

        .nd-receipt-total .ra {
            font-size: 20px;
            font-weight: 800;
            color: #2c7a7b;
        }

        /* ── Action buttons ── */
        .nd-actions {
            display: flex;
            gap: 10px;
            padding: 16px 24px 20px;
        }

        .nd-btn {
            flex: 1;
            padding: 13px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .2s, transform .1s;
        }

        .nd-btn:active {
            transform: scale(0.97);
        }

        .nd-btn.primary {
            color: #fff;
        }

        .nd-btn.primary.success {
            background: #16a34a;
        }

        .nd-btn.primary.error {
            background: #dc2626;
        }

        .nd-btn.primary.warning {
            background: #d97706;
        }

        .nd-btn.secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .nd-btn:hover {
            opacity: 0.88;
        }
    </style>
</head>

<body>

    <!-- ════════════════════════════════════════
       GLOBAL NOTIFICATION DIALOG
       ════════════════════════════════════════ -->
    <div class="nd-overlay" id="ndOverlay">
        <div class="nd-card" id="ndCard">

            <div class="nd-stripe" id="ndStripe"></div>

            <div class="nd-body">
                <div class="nd-icon" id="ndIcon"><!-- injected --></div>
                <p class="nd-title" id="ndTitle"></p>
                <p class="nd-msg" id="ndMsg"></p>

                <!-- receipt (only for booking success) -->
                <div class="nd-receipt" id="ndReceipt" style="display:none">
                    <div class="nd-receipt-row"><span>Motorcycle</span> <span class="rv" id="rcBike"></span></div>
                    <div class="nd-receipt-row"><span>Date</span> <span class="rv" id="rcDate"></span></div>
                    <div class="nd-receipt-row"><span>Time</span> <span class="rv" id="rcTime"></span></div>
                    <div class="nd-receipt-total">
                        <span class="rl">Total</span>
                        <span class="ra" id="rcTotal"></span>
                    </div>
                </div>
            </div>

            <div class="nd-actions" id="ndActions"><!-- injected --></div>

        </div>
    </div>


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


    <!-- BOOKING MODAL -->
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
                <input type="hidden" id="modalBikeId" name="motorcycle_id">
                <input type="hidden" id="calculatedTotalCost" name="total_cost">
                <input type="hidden" id="hiddenStartDatetime" name="start_date">
                <input type="hidden" id="hiddenEndDatetime" name="end_date">

                <label class="form-label" for="renterName">Renter's Full Name</label>
                <input class="modal-input" id="renterName" type="text" name="full_name"
                    placeholder="e.g. Juan Dela Cruz" required>

                <label class="form-label">Booking Date</label>
                <div class="date-input-wrap">
                    <input type="date" id="bookingDate" name="booking_date"
                        onchange="onDateChange()" required>
                </div>

                <div class="time-slot-row">
                    <div>
                        <label class="form-label" for="startTimeSelect">Start Time</label>
                        <select class="time-select" id="startTimeSelect" onchange="onStartTimeChange()" required>
                            <option value="">-- Pick --</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="endTimeSelect">End Time</label>
                        <select class="time-select" id="endTimeSelect" onchange="onEndTimeChange()" required disabled>
                            <option value="">-- Pick start first --</option>
                        </select>
                    </div>
                </div>
                <p class="time-note">Minimum rental: 1 hour • slots every 30 minutes</p>

                <div class="booking-error" id="bookingError"></div>

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
     NOTIFICATION DIALOG SYSTEM
  ================================================= */
        const ND_ICONS = {
            success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>`,
            error: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
            warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`
        };

        let _ndReloadOnClose = false;

        /**
         * openNotifDialog(options)
         * options: {
         *   type:    'success' | 'error' | 'warning',
         *   title:   string,
         *   message: string,
         *   receipt: { bike, date, time, total } | null,   // shows receipt strip
         *   primaryLabel:   string,           // default: 'Got it'
         *   secondaryLabel: string | null,    // shows a second button if set
         *   onPrimary:   fn | 'reload',       // callback or 'reload'
         *   onSecondary: fn | string (href),
         * }
         */
        function openNotifDialog(opts) {
            const type = opts.type || 'success';
            _ndReloadOnClose = false;

            document.getElementById('ndStripe').className = 'nd-stripe ' + type;

            const iconEl = document.getElementById('ndIcon');
            iconEl.className = 'nd-icon ' + type;
            iconEl.innerHTML = ND_ICONS[type];

            document.getElementById('ndTitle').textContent = opts.title || '';
            document.getElementById('ndMsg').textContent = opts.message || '';

            // Receipt strip
            const receipt = document.getElementById('ndReceipt');
            if (opts.receipt) {
                document.getElementById('rcBike').textContent = opts.receipt.bike || '';
                document.getElementById('rcDate').textContent = opts.receipt.date || '';
                document.getElementById('rcTime').textContent = opts.receipt.time || '';
                document.getElementById('rcTotal').textContent = opts.receipt.total || '';
                receipt.style.display = 'block';
            } else {
                receipt.style.display = 'none';
            }

            // Buttons
            const actionsEl = document.getElementById('ndActions');
            actionsEl.innerHTML = '';

            // Primary button
            const primBtn = document.createElement('button');
            primBtn.className = `nd-btn primary ${type}`;
            primBtn.textContent = opts.primaryLabel || 'Got it';
            primBtn.onclick = () => {
                closeNotifDialog();
                if (opts.onPrimary === 'reload') {
                    location.reload();
                } else if (typeof opts.onPrimary === 'function') {
                    opts.onPrimary();
                }
            };
            actionsEl.appendChild(primBtn);

            // Optional secondary button
            if (opts.secondaryLabel) {
                const secBtn = document.createElement('button');
                secBtn.className = 'nd-btn secondary';
                secBtn.textContent = opts.secondaryLabel;
                secBtn.onclick = () => {
                    closeNotifDialog();
                    if (typeof opts.onSecondary === 'string') {
                        window.location.href = opts.onSecondary;
                    } else if (typeof opts.onSecondary === 'function') {
                        opts.onSecondary();
                    }
                };
                actionsEl.appendChild(secBtn);
            }

            document.getElementById('ndOverlay').classList.add('show');
        }

        function closeNotifDialog() {
            document.getElementById('ndOverlay').classList.remove('show');
        }

        // Close on backdrop click
        document.getElementById('ndOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeNotifDialog();
        });

        // Close on Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeNotifDialog();
        });


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
        const favCounter = document.getElementById('favoritesCount');

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
                        if (data.success) {
                            favCounter.innerText = parseInt(favCounter.innerText) + (data.action === 'added' ? 1 : -1);
                        } else {
                            // Revert icon
                            this.classList.toggle('hearted', was);
                            this.innerText = was ? '❤' : '♡';
                            openNotifDialog({
                                type: 'warning',
                                title: 'Could not update favorite',
                                message: data.message || 'Something went wrong. Please try again.',
                                primaryLabel: 'OK'
                            });
                        }
                    })
                    .catch(() => {
                        this.classList.toggle('hearted', was);
                        this.innerText = was ? '❤' : '♡';
                        openNotifDialog({
                            type: 'error',
                            title: 'Network error',
                            message: 'Unable to update your favorites. Please check your connection and try again.',
                            primaryLabel: 'OK'
                        });
                    });
            });
        });


        /* =================================================
           BOOKING MODAL — Time Slot Logic
        ================================================= */
        let currentHourlyRate = 0;

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

        function formatTime12(h, m) {
            const ampm = h >= 12 ? 'PM' : 'AM';
            const hour = h % 12 || 12;
            return `${hour}:${String(m).padStart(2,'0')} ${ampm}`;
        }

        function todayStr() {
            const d = new Date();
            return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
        }

        function currentMinutesRoundedUp() {
            const now = new Date();
            const raw = now.getHours() * 60 + now.getMinutes();
            return Math.ceil(raw / 30) * 30;
        }

        function slotToMinutes(value) {
            const [h, m] = value.split(':').map(Number);
            return h * 60 + m;
        }

        function populateStartSlots() {
            const selectedDate = document.getElementById('bookingDate').value;
            const isToday = selectedDate === todayStr();
            const minMinutes = isToday ? currentMinutesRoundedUp() : 0;
            const allSlots = buildAllSlots();
            const startSel = document.getElementById('startTimeSelect');
            startSel.innerHTML = '';

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = '-- Select start time --';
            startSel.appendChild(placeholder);

            let firstFutureVal = null;
            allSlots.forEach(slot => {
                if (slotToMinutes(slot.value) < minMinutes) return;
                const opt = document.createElement('option');
                opt.value = slot.value;
                opt.textContent = slot.label;
                startSel.appendChild(opt);
                if (!firstFutureVal) firstFutureVal = slot.value;
            });

            if (firstFutureVal) startSel.value = firstFutureVal;
            resetEndSlots();
            if (firstFutureVal) populateEndSlots(firstFutureVal);
        }

        function resetEndSlots() {
            const endSel = document.getElementById('endTimeSelect');
            endSel.innerHTML = '<option value="">-- Pick start first --</option>';
            endSel.disabled = true;
            hideSummary();
            disableConfirm();
        }

        function populateEndSlots(startValue) {
            const startMin = slotToMinutes(startValue);
            const minEnd = startMin + 60;
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
                if (slotMin < minEnd) return;
                const opt = document.createElement('option');
                opt.value = slot.value;
                const diffH = Math.floor((slotMin - startMin) / 60);
                const diffM = (slotMin - startMin) % 60;
                const durLabel = diffM === 0 ? `${diffH}h` : `${diffH}h ${diffM}m`;
                opt.textContent = `${slot.label}  (${durLabel})`;
                endSel.appendChild(opt);
                count++;
            });

            if (count === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'No slots available today';
                endSel.appendChild(opt);
                endSel.disabled = true;
                showError('Start time is too late for a 1-hour rental today. Please choose an earlier start time or a different date.');
                return;
            }

            endSel.disabled = false;
            endSel.value = '';
            hideError();
        }

        function onDateChange() {
            populateStartSlots();
            hideSummary();
            disableConfirm();
            hideError();
        }

        function onStartTimeChange() {
            const startVal = document.getElementById('startTimeSelect').value;
            if (!startVal) {
                resetEndSlots();
                return;
            }
            populateEndSlots(startVal);
            hideSummary();
            disableConfirm();
            hideError();
        }

        function onEndTimeChange() {
            const startVal = document.getElementById('startTimeSelect').value;
            const endVal = document.getElementById('endTimeSelect').value;
            if (!startVal || !endVal) {
                hideSummary();
                disableConfirm();
                return;
            }
            if (slotToMinutes(endVal) <= slotToMinutes(startVal)) {
                showError('End time must be after start time.');
                hideSummary();
                disableConfirm();
                return;
            }
            hideError();
            updateSummary(startVal, endVal);
            enableConfirm();
        }

        function updateSummary(startVal, endVal) {
            const startMin = slotToMinutes(startVal);
            const endMin = slotToMinutes(endVal);
            const diffMin = endMin - startMin;
            const hours = diffMin / 60;
            const total = (hours * currentHourlyRate).toFixed(2);
            const hPart = Math.floor(hours);
            const mPart = diffMin % 60;
            const durStr = hPart > 0 ?
                (mPart > 0 ? `${hPart} hr ${mPart} min` : `${hPart} hr${hPart > 1 ? 's' : ''}`) :
                `${mPart} min`;

            document.getElementById('summaryDuration').textContent = durStr;
            document.getElementById('summaryRate').textContent = `₱${currentHourlyRate.toFixed(2)}/hr`;
            document.getElementById('summaryTotal').textContent = `₱${total}`;
            document.getElementById('calculatedTotalCost').value = total;
            document.getElementById('bookingSummary').classList.add('visible');
        }

        function hideSummary() {
            document.getElementById('bookingSummary').classList.remove('visible');
            document.getElementById('calculatedTotalCost').value = '';
        }

        function showError(msg) {
            const el = document.getElementById('bookingError');
            el.textContent = msg;
            el.classList.add('visible');
        }

        function hideError() {
            document.getElementById('bookingError').classList.remove('visible');
        }

        function enableConfirm() {
            document.getElementById('confirmBtn').disabled = false;
        }

        function disableConfirm() {
            document.getElementById('confirmBtn').disabled = true;
        }

        function openBookingModal(id, model, plate, hourlyRate, image) {
            currentHourlyRate = parseFloat(hourlyRate);
            document.getElementById('modalBikeId').value = id;
            document.getElementById('modalBikeModel').innerText = model;
            document.getElementById('modalBikePlate').innerText = `Plate: ${plate}`;
            document.getElementById('modalBikeHourlyRate').innerText = `₱${currentHourlyRate.toFixed(2)} / hour`;
            document.getElementById('modalBikeImage').src = image;
            document.getElementById('modalBookingForm').reset();
            document.getElementById('modalBikeId').value = id;
            const dateInput = document.getElementById('bookingDate');
            dateInput.value = todayStr();
            dateInput.min = todayStr();
            hideSummary();
            disableConfirm();
            hideError();
            populateStartSlots();
            document.getElementById('bookNowModal').style.display = 'flex';
        }

        function closeBookingModal() {
            document.getElementById('bookNowModal').style.display = 'none';
        }

        document.getElementById('bookNowModal').addEventListener('click', function(e) {
            if (e.target === this) closeBookingModal();
        });


        /* =================================================
           SUBMIT BOOKING  →  modern dialog on result
        ================================================= */
        document.getElementById('modalBookingForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const date = document.getElementById('bookingDate').value;
            const startVal = document.getElementById('startTimeSelect').value;
            const endVal = document.getElementById('endTimeSelect').value;
            const name = document.getElementById('renterName').value.trim();

            if (!date || !startVal || !endVal || !name) {
                showError('Please fill in all fields before confirming.');
                return;
            }

            const startDatetime = `${date} ${startVal}:00`;
            const endDatetime = `${date} ${endVal}:00`;

            document.getElementById('hiddenStartDatetime').value = startDatetime;
            document.getElementById('hiddenEndDatetime').value = endDatetime;

            const btn = document.getElementById('confirmBtn');
            btn.disabled = true;
            btn.textContent = 'Processing…';

            const formData = new FormData(this);
            formData.set('start_date', startDatetime);
            formData.set('end_date', endDatetime);

            const bikeName = document.getElementById('modalBikeModel').innerText;
            const totalCost = document.getElementById('calculatedTotalCost').value;

            fetch('booking.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    btn.disabled = false;
                    btn.textContent = 'Confirm Booking';

                    if (data.success) {
                        // Close booking modal first
                        closeBookingModal();

                        // Format a friendly date string
                        const [yr, mo, dy] = date.split('-');
                        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        const friendlyDate = `${months[parseInt(mo)-1]} ${parseInt(dy)}, ${yr}`;

                        // Convert time to 12-hr for receipt
                        function to12(t) {
                            const [h, m] = t.split(':').map(Number);
                            const ap = h >= 12 ? 'PM' : 'AM';
                            return `${h % 12 || 12}:${String(m).padStart(2,'0')} ${ap}`;
                        }

                        openNotifDialog({
                            type: 'success',
                            title: 'Booking Confirmed!',
                            message: `Your ride has been reserved. We'll see you on ${friendlyDate}!`,
                            receipt: {
                                bike: bikeName,
                                date: friendlyDate,
                                time: `${to12(startVal)} – ${to12(endVal)}`,
                                total: `₱${parseFloat(totalCost).toFixed(2)}`
                            },
                            primaryLabel: 'View My Bookings',
                            secondaryLabel: 'Back to Browse',
                            onPrimary: () => {
                                window.location.href = 'my-bookings.php';
                            },
                            onSecondary: 'reload'
                        });

                    } else {
                        openNotifDialog({
                            type: 'error',
                            title: 'Booking Failed',
                            message: data.message || 'Something went wrong. Please try again.',
                            primaryLabel: 'Try Again',
                            onPrimary: () => {
                                document.getElementById('bookNowModal').style.display = 'flex';
                            }
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    btn.disabled = false;
                    btn.textContent = 'Confirm Booking';
                    openNotifDialog({
                        type: 'error',
                        title: 'Network Error',
                        message: 'Could not reach the server. Please check your connection and try again.',
                        primaryLabel: 'OK'
                    });
                });
        });
    </script>
</body>

</html>
