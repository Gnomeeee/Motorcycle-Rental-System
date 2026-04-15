<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: ../login.php');
  exit;
}

require_once "../Database/dbconnect.php";

$user_id        = $_SESSION['user_id'];
$reservation_id = (int)($_GET['id'] ?? 0);

$success_message = '';
$error_message   = '';
$current_status  = '';
$reservation     = [];

/* =================================================
   Handle Form Submission
================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $new_start = trim($_POST['start_date'] ?? ''); // "YYYY-MM-DD HH:MM:00"
  $new_end   = trim($_POST['end_date']   ?? ''); // "YYYY-MM-DD HH:MM:00"

  $start_ts = strtotime($new_start);
  $end_ts   = strtotime($new_end);

  // --- Validation ---
  if (!$reservation_id || !$new_start || !$new_end) {
    $error_message = "Missing required fields.";

  } elseif (!$start_ts || !$end_ts) {
    $error_message = "Invalid date or time format.";

  } elseif ($end_ts <= $start_ts) {
    $error_message = "End time must be later than start time.";

  } elseif (($end_ts - $start_ts) < 3600) {
    $error_message = "Minimum rental duration is 1 hour.";

  } elseif ($start_ts < time() - 300) {
    $error_message = "Start time cannot be in the past.";

  } else {
    // --- Conflict check (exclude current reservation) ---
    $conflict_sql = "
        SELECT reservation_id
        FROM   reservations
        WHERE  motorcycle_id = (
                   SELECT motorcycle_id FROM reservations
                   WHERE reservation_id = ? AND user_id = ? LIMIT 1
               )
          AND  reservation_id != ?
          AND  status IN ('Pending', 'Approved')
          AND  start_date < ?
          AND  end_date   > ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($conflict_sql);
    $stmt->bind_param("iiiss", $reservation_id, $user_id, $reservation_id, $new_end, $new_start);
    $stmt->execute();
    $conflict = $stmt->get_result();
    $stmt->close();

    if ($conflict->num_rows > 0) {
      $error_message = "This motorcycle is already booked for part of the new time slot. Please choose a different time.";
    } else {
      // --- Recalculate cost ---
      $duration_hours = ($end_ts - $start_ts) / 3600;

      $stmt = $conn->prepare("
          SELECT m.rate_per_hour, m.motorcycle_id
          FROM   reservations r
          JOIN   motorcycles m ON m.motorcycle_id = r.motorcycle_id
          WHERE  r.reservation_id = ? AND r.user_id = ?
          LIMIT  1
      ");
      $stmt->bind_param("ii", $reservation_id, $user_id);
      $stmt->execute();
      $res  = $stmt->get_result();
      $bike = $res->fetch_assoc();
      $stmt->close();

      if ($bike) {
        $new_total = round($duration_hours * (float)$bike['rate_per_hour'], 2);

        // --- Update reservation ---
        $stmt = $conn->prepare("
            UPDATE reservations
            SET    start_date = ?,
                   end_date   = ?,
                   total_cost = ?,
                   status     = 'Pending'
            WHERE  reservation_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ssdii", $new_start, $new_end, $new_total, $reservation_id, $user_id);
        $stmt->execute();
        $stmt->close();

        $success_message = "✅ Reservation rescheduled successfully.";
      } else {
        $error_message = "Reservation not found or access denied.";
      }
    }
  }
}

/* =================================================
   Fetch Current Reservation
================================================= */
if ($reservation_id) {
  $stmt = $conn->prepare("
      SELECT r.start_date, r.end_date, r.status, r.total_cost,
             m.rate_per_hour, m.model
      FROM   reservations r
      JOIN   motorcycles  m ON m.motorcycle_id = r.motorcycle_id
      WHERE  r.reservation_id = ? AND r.user_id = ?
      LIMIT  1
  ");
  $stmt->bind_param("ii", $reservation_id, $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    $reservation    = $row;
    $current_status = $row['status'];
  }
  $stmt->close();
}

// Redirect if not found
if (!$reservation) {
  header('Location: my-bookings.php');
  exit;
}

// Block reschedule if status is not Pending or Approved
$can_reschedule = in_array($current_status, ['Pending', 'Approved']);

$status_colors = [
  'Pending'   => '#f59e0b',
  'Approved'  => '#2563eb',
  'Rejected'  => '#ef4444',
  'Completed' => '#6366f1',
];
$badge_color = $status_colors[$current_status] ?? '#9ca3af';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reschedule Reservation</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', system-ui, sans-serif;
      background: rgba(0,0,0,0.45);
      backdrop-filter: blur(5px);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      padding: 16px;
    }

    /* ---- Card ---- */
    .modal-card {
      background: #fff;
      border-radius: 24px;
      padding: 28px 24px 24px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 16px 48px rgba(0,0,0,0.22);
      position: relative;
      animation: slideUp 0.3s cubic-bezier(0.34,1.56,0.64,1);
      max-height: 92vh;
      overflow-y: auto;
    }

    @keyframes slideUp {
      from { transform: translateY(40px); opacity: 0; }
      to   { transform: translateY(0);    opacity: 1; }
    }

    /* ---- Close button ---- */
    .close-btn {
      position: absolute;
      top: 14px; right: 14px;
      background: #f5f5f5;
      border: none;
      width: 32px; height: 32px;
      border-radius: 50%;
      font-size: 15px;
      cursor: pointer;
      color: #555;
      display: flex; align-items: center; justify-content: center;
      transition: background 0.2s, color 0.2s;
    }
    .close-btn:hover { background: #e0e0e0; color: #111; }

    /* ---- Header ---- */
    .modal-header {
      text-align: center;
      margin-bottom: 20px;
    }
    .modal-header h2 {
      font-size: 20px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 8px;
    }
    .bike-name {
      font-size: 13px;
      color: #888;
      margin-bottom: 10px;
    }
    .status-badge {
      display: inline-block;
      padding: 4px 14px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      color: #fff;
      letter-spacing: 0.4px;
    }

    /* ---- Current booking info ---- */
    .current-info {
      background: #f8f8f8;
      border-radius: 12px;
      padding: 12px 14px;
      margin-bottom: 20px;
      font-size: 13px;
      color: #555;
    }
    .current-info .ci-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 4px;
    }
    .current-info .ci-row:last-child { margin-bottom: 0; }
    .current-info .ci-val { font-weight: 600; color: #1a1a1a; }

    /* ---- Divider ---- */
    hr { border: none; border-top: 1px solid #f0f0f0; margin: 0 0 18px; }

    /* ---- Form labels ---- */
    .form-label {
      display: block;
      font-size: 12px;
      font-weight: 600;
      color: #555;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 6px;
    }

    /* ---- Date input ---- */
    .date-input {
      width: 100%;
      padding: 11px 14px;
      border-radius: 10px;
      border: 1.5px solid #e5e5e5;
      font-size: 14px;
      color: #1a1a1a;
      background: #fafafa;
      margin-bottom: 14px;
      cursor: pointer;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .date-input:focus {
      outline: none;
      border-color: #38b2ac;
      box-shadow: 0 0 0 3px rgba(56,178,172,0.12);
      background: #fff;
    }

    /* ---- Time slot row ---- */
    .time-slot-row {
      display: flex;
      gap: 10px;
      margin-bottom: 8px;
    }
    .time-slot-row > div { flex: 1; }

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
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%23888' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 12px center;
      background-size: 10px;
      padding-right: 30px;
      box-sizing: border-box;
    }
    .time-select:focus {
      outline: none;
      border-color: #38b2ac;
      box-shadow: 0 0 0 3px rgba(56,178,172,0.12);
      background-color: #fff;
    }
    .time-select:disabled { opacity: 0.45; cursor: not-allowed; }

    .time-note {
      font-size: 12px;
      color: #aaa;
      text-align: center;
      margin-bottom: 14px;
    }

    /* ---- Summary card ---- */
    .booking-summary {
      background: #f0fafa;
      border: 1.5px solid #b2dfdb;
      border-radius: 12px;
      padding: 12px 16px;
      margin-bottom: 16px;
      display: none;
    }
    .booking-summary.visible { display: block; }
    .summary-row {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      color: #444;
      margin-bottom: 5px;
    }
    .summary-row .val { font-weight: 600; color: #1a1a1a; }
    .summary-total {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 10px;
      padding-top: 10px;
      border-top: 1px dashed #b2dfdb;
    }
    .summary-total .label-total { font-size: 13px; font-weight: 600; color: #333; }
    .summary-total .val-total   { font-size: 22px; font-weight: 800; color: #2c7a7b; }

    /* ---- Feedback messages ---- */
    .alert {
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 14px;
      display: none;
    }
    .alert.visible { display: block; }
    .alert-error   { background: #fff5f5; border: 1px solid #fc8181; color: #c53030; }
    .alert-success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }

    /* ---- Disabled overlay for non-reschedulable statuses ---- */
    .locked-notice {
      background: #fef9c3;
      border: 1px solid #fcd34d;
      border-radius: 10px;
      padding: 12px 14px;
      font-size: 13px;
      color: #78350f;
      text-align: center;
      margin-bottom: 14px;
    }

    /* ---- Action buttons ---- */
    .modal-actions {
      display: flex;
      gap: 10px;
      margin-top: 4px;
    }
    .btn {
      flex: 1;
      padding: 13px;
      border-radius: 12px;
      font-size: 14px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      transition: opacity 0.2s, transform 0.1s;
      text-align: center;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .btn:active { transform: scale(0.98); }

    .btn-submit {
      background: linear-gradient(135deg, #38b2ac, #2c7a7b);
      color: #fff;
    }
    .btn-submit:hover   { opacity: 0.9; }
    .btn-submit:disabled { background: #ccc; cursor: not-allowed; opacity: 1; }

    .btn-cancel {
      background: #f0f0f0;
      color: #444;
    }
    .btn-cancel:hover { background: #e0e0e0; }
  </style>
</head>
<body>

<div class="modal-card">

  <button class="close-btn" onclick="window.location.href='my-bookings.php'" title="Back">✕</button>

  <!-- Header -->
  <div class="modal-header">
    <h2>Reschedule Reservation</h2>
    <p class="bike-name"><?= htmlspecialchars($reservation['model']) ?></p>
    <span class="status-badge" style="background:<?= $badge_color ?>">
      <?= htmlspecialchars($current_status) ?>
    </span>
  </div>

  <!-- Current booking snapshot -->
  <div class="current-info">
    <div class="ci-row">
      <span>Current Start</span>
      <span class="ci-val"><?= date('M j, Y  g:i A', strtotime($reservation['start_date'])) ?></span>
    </div>
    <div class="ci-row">
      <span>Current End</span>
      <span class="ci-val"><?= date('M j, Y  g:i A', strtotime($reservation['end_date'])) ?></span>
    </div>
    <div class="ci-row">
      <span>Current Total</span>
      <span class="ci-val">₱<?= number_format((float)$reservation['total_cost'], 2) ?></span>
    </div>
  </div>

  <hr>

  <?php if (!$can_reschedule): ?>
    <!-- Locked state -->
    <div class="locked-notice">
      ⚠️ This reservation is <strong><?= htmlspecialchars($current_status) ?></strong>
      and can no longer be rescheduled.
    </div>
    <a href="my-bookings.php" class="btn btn-cancel" style="display:flex;justify-content:center">
      ← Back to My Bookings
    </a>

  <?php else: ?>

    <!-- PHP-side error (form validation) -->
    <?php if ($error_message): ?>
      <div class="alert alert-error visible"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- PHP-side success -->
    <?php if ($success_message): ?>
      <div class="alert alert-success visible"><?= $success_message ?></div>
      <script>setTimeout(() => window.location.href = 'my-bookings.php', 2500);</script>
    <?php endif; ?>

    <!-- JS-side error -->
    <div class="alert alert-error" id="jsError"></div>

    <form id="rescheduleForm" method="post">
      <!-- Hidden datetime fields posted to PHP -->
      <input type="hidden" id="hiddenStart" name="start_date">
      <input type="hidden" id="hiddenEnd"   name="end_date">

      <!-- Date picker -->
      <label class="form-label">New Booking Date</label>
      <input class="date-input" type="date" id="bookingDate"
             onchange="onDateChange()" required>

      <!-- Time dropdowns -->
      <div class="time-slot-row">
        <div>
          <label class="form-label" for="startSel">Start Time</label>
          <select class="time-select" id="startSel" onchange="onStartChange()" required>
            <option value="">-- Pick --</option>
          </select>
        </div>
        <div>
          <label class="form-label" for="endSel">End Time</label>
          <select class="time-select" id="endSel" onchange="onEndChange()" required disabled>
            <option value="">-- Pick start first --</option>
          </select>
        </div>
      </div>
      <p class="time-note">Minimum rental: 1 hour &bull; slots every 30 minutes</p>

      <!-- New booking summary -->
      <div class="booking-summary" id="bookingSummary">
        <div class="summary-row">
          <span>New Duration</span>
          <span class="val" id="sumDuration">—</span>
        </div>
        <div class="summary-row">
          <span>Rate</span>
          <span class="val" id="sumRate">—</span>
        </div>
        <div class="summary-total">
          <span class="label-total">New Total</span>
          <span class="val-total" id="sumTotal">₱0.00</span>
        </div>
      </div>

      <!-- Actions -->
      <div class="modal-actions">
        <button type="submit" class="btn btn-submit" id="submitBtn" disabled>
          Confirm Reschedule
        </button>
        <a href="my-bookings.php" class="btn btn-cancel">Cancel</a>
      </div>
    </form>

  <?php endif; ?>
</div>


<script>
/* ================================================
   Constants injected from PHP
================================================ */
const HOURLY_RATE     = <?= (float)$reservation['rate_per_hour'] ?>;

// Pre-fill with current reservation times so user sees their original choice
const CURRENT_DATE    = <?= json_encode(substr($reservation['start_date'], 0, 10)) ?>;
const CURRENT_START   = <?= json_encode(substr($reservation['start_date'], 11, 5)) ?>; // "HH:MM"
const CURRENT_END     = <?= json_encode(substr($reservation['end_date'],   11, 5)) ?>; // "HH:MM"

/* ================================================
   Helpers
================================================ */
function todayStr() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
}

function currentMinutesRoundedUp() {
  const now = new Date();
  const raw = now.getHours() * 60 + now.getMinutes();
  return Math.ceil(raw / 30) * 30;
}

function slotToMinutes(v) {
  const [h, m] = v.split(':').map(Number);
  return h * 60 + m;
}

function formatTime12(h, m) {
  const ampm = h >= 12 ? 'PM' : 'AM';
  const hour = h % 12 || 12;
  return `${hour}:${String(m).padStart(2,'0')} ${ampm}`;
}

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

/* ================================================
   Populate Start dropdown
================================================ */
function populateStartSlots(preselect) {
  const selectedDate = document.getElementById('bookingDate').value;
  const isToday      = selectedDate === todayStr();
  const minMin       = isToday ? currentMinutesRoundedUp() : 0;

  const sel  = document.getElementById('startSel');
  sel.innerHTML = '';

  const ph = document.createElement('option');
  ph.value = ''; ph.textContent = '-- Select start time --';
  sel.appendChild(ph);

  let firstVal = null;
  buildAllSlots().forEach(slot => {
    if (slotToMinutes(slot.value) < minMin) return;
    const opt = document.createElement('option');
    opt.value       = slot.value;
    opt.textContent = slot.label;
    sel.appendChild(opt);
    if (!firstVal) firstVal = slot.value;
  });

  // Prefer preselected value, fall back to first future slot
  if (preselect && slotToMinutes(preselect) >= minMin) {
    sel.value = preselect;
  } else if (firstVal) {
    sel.value = firstVal;
  }

  resetEndSlots();
  if (sel.value) populateEndSlots(sel.value, preselect ? CURRENT_END : null);
}

/* ================================================
   Populate End dropdown
================================================ */
function populateEndSlots(startValue, preselect) {
  const startMin = slotToMinutes(startValue);
  const minEnd   = startMin + 60;

  const sel = document.getElementById('endSel');
  sel.innerHTML = '';

  const ph = document.createElement('option');
  ph.value = ''; ph.textContent = '-- Select end time --';
  sel.appendChild(ph);

  let count = 0;
  buildAllSlots().forEach(slot => {
    const sm = slotToMinutes(slot.value);
    if (sm < minEnd) return;
    const opt = document.createElement('option');
    opt.value = slot.value;
    const diffMin  = sm - startMin;
    const diffH    = Math.floor(diffMin / 60);
    const diffM    = diffMin % 60;
    const durLabel = diffM === 0 ? `${diffH}h` : `${diffH}h ${diffM}m`;
    opt.textContent = `${slot.label}  (${durLabel})`;
    sel.appendChild(opt);
    count++;
  });

  if (count === 0) {
    sel.disabled = true;
    showError("Start time is too late for a 1-hour rental today. Please choose an earlier start or a different date.");
    return;
  }

  sel.disabled = false;

  if (preselect && slotToMinutes(preselect) >= minEnd) {
    sel.value = preselect;
    onEndChange(); // trigger summary
  } else {
    sel.value = '';
  }

  hideError();
}

function resetEndSlots() {
  const sel = document.getElementById('endSel');
  sel.innerHTML = '<option value="">-- Pick start first --</option>';
  sel.disabled = true;
  hideSummary();
  disableSubmit();
}

/* ================================================
   Event handlers
================================================ */
function onDateChange() {
  populateStartSlots(null);
  hideSummary(); disableSubmit(); hideError();
}

function onStartChange() {
  const v = document.getElementById('startSel').value;
  if (!v) { resetEndSlots(); return; }
  populateEndSlots(v, null);
  hideSummary(); disableSubmit(); hideError();
}

function onEndChange() {
  const startVal = document.getElementById('startSel').value;
  const endVal   = document.getElementById('endSel').value;
  if (!startVal || !endVal) { hideSummary(); disableSubmit(); return; }

  const sm = slotToMinutes(startVal);
  const em = slotToMinutes(endVal);
  if (em <= sm) { showError("End time must be after start time."); hideSummary(); disableSubmit(); return; }

  hideError();
  updateSummary(startVal, endVal);
  enableSubmit();
}

/* ================================================
   Summary card
================================================ */
function updateSummary(startVal, endVal) {
  const diffMin  = slotToMinutes(endVal) - slotToMinutes(startVal);
  const hours    = diffMin / 60;
  const total    = (hours * HOURLY_RATE).toFixed(2);

  const hPart = Math.floor(hours);
  const mPart = diffMin % 60;
  const durStr = hPart > 0
    ? (mPart > 0 ? `${hPart} hr ${mPart} min` : `${hPart} hr${hPart > 1 ? 's' : ''}`)
    : `${mPart} min`;

  document.getElementById('sumDuration').textContent = durStr;
  document.getElementById('sumRate').textContent     = `₱${HOURLY_RATE.toFixed(2)}/hr`;
  document.getElementById('sumTotal').textContent    = `₱${total}`;
  document.getElementById('bookingSummary').classList.add('visible');
}

function hideSummary() { document.getElementById('bookingSummary').classList.remove('visible'); }

/* ================================================
   Error helpers
================================================ */
function showError(msg) {
  const el = document.getElementById('jsError');
  el.textContent = msg;
  el.classList.add('visible');
}
function hideError() { document.getElementById('jsError').classList.remove('visible'); }

/* ================================================
   Submit button state
================================================ */
function enableSubmit()  { document.getElementById('submitBtn').disabled = false; }
function disableSubmit() { document.getElementById('submitBtn').disabled = true;  }

/* ================================================
   Form submit — inject hidden datetime fields
================================================ */
document.getElementById('rescheduleForm').addEventListener('submit', function(e) {
  const date     = document.getElementById('bookingDate').value;
  const startVal = document.getElementById('startSel').value;
  const endVal   = document.getElementById('endSel').value;

  if (!date || !startVal || !endVal) {
    e.preventDefault();
    showError('Please fill in all fields before confirming.');
    return;
  }

  document.getElementById('hiddenStart').value = `${date} ${startVal}:00`;
  document.getElementById('hiddenEnd').value   = `${date} ${endVal}:00`;

  const btn = document.getElementById('submitBtn');
  btn.disabled    = true;
  btn.textContent = 'Processing…';
});

/* ================================================
   On page load — pre-fill with current booking
================================================ */
(function init() {
  const dateInput = document.getElementById('bookingDate');
  if (!dateInput) return; // locked state — nothing to init

  dateInput.min = todayStr();

  // Use current booking date as default
  dateInput.value = CURRENT_DATE >= todayStr() ? CURRENT_DATE : todayStr();

  // Pre-fill dropdowns with existing start/end so user sees what they're changing
  populateStartSlots(CURRENT_START);
  // Note: populateEndSlots with preselect is called inside populateStartSlots
})();
</script>
</body>
</html>