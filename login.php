<?php
session_start();
require_once "./Database/dbconnect.php";

$dialog_message = '';
$dialog_type    = '';

if (isset($_SESSION['error'])) {
    $dialog_message = $_SESSION['error'];
    $dialog_type    = 'error';
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $dialog_message = $_SESSION['success'];
    $dialog_type    = 'success';
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MotoRide Login</title>
    <link rel="stylesheet" href="./Assets/Styles/cus-login.css">

    <style>
        /* ── Dialog Overlay ── */
        .dialog-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            padding: 16px;
        }

        .dialog-overlay.show {
            display: flex;
            animation: fadeOverlay .2s ease;
        }

        @keyframes fadeOverlay {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* ── Dialog Card ── */
        .dialog-card {
            background: #fff;
            border-radius: 20px;
            padding: 32px 28px 24px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.18);
            text-align: center;
            position: relative;
            animation: popIn .3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes popIn {
            from {
                transform: scale(0.88) translateY(20px);
                opacity: 0;
            }

            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }

        /* ── Icon Circle ── */
        .dialog-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dialog-icon svg {
            width: 30px;
            height: 30px;
        }

        .dialog-card.is-error .dialog-icon {
            background: #fee2e2;
        }

        .dialog-card.is-error .dialog-icon svg {
            color: #dc2626;
        }

        .dialog-card.is-success .dialog-icon {
            background: #dcfce7;
        }

        .dialog-card.is-success .dialog-icon svg {
            color: #16a34a;
        }

        /* ── Title ── */
        .dialog-title {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 8px;
        }

        /* ── Body text ── */
        .dialog-body {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
            margin: 0 0 24px;
        }

        /* ── Dismiss button ── */
        .dialog-btn {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .2s, transform .1s;
            letter-spacing: 0.2px;
        }

        .dialog-btn:active {
            transform: scale(0.97);
        }

        .dialog-card.is-error .dialog-btn {
            background: #dc2626;
            color: #fff;
        }

        .dialog-card.is-success .dialog-btn {
            background: #16a34a;
            color: #fff;
        }

        .dialog-btn:hover {
            opacity: 0.88;
        }

        /* ── Top accent bar ── */
        .dialog-card::before {
            content: '';
            display: block;
            height: 4px;
            border-radius: 20px 20px 0 0;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
        }

        .dialog-card.is-error::before {
            background: #dc2626;
        }

        .dialog-card.is-success::before {
            background: #16a34a;
        }
    </style>
</head>

<body>

    <!-- ══════════════════════════════
       MODERN DIALOG (replaces alerts)
       ══════════════════════════════ -->
    <div class="dialog-overlay" id="dialogOverlay">
        <div class="dialog-card" id="dialogCard">

            <div class="dialog-icon" id="dialogIcon">
                <!-- icon injected by JS -->
            </div>

            <h2 class="dialog-title" id="dialogTitle"></h2>
            <p class="dialog-body" id="dialogBody"></p>

            <button class="dialog-btn" onclick="closeDialog()">Got it</button>

        </div>
    </div>


    <div class="container">

        <!-- LEFT SIDE BACKGROUND PANEL -->
        <div class="left-panel">
            <div class="back-home" onclick="window.location.href='index.php'">
                <span>&larr;</span> Back to Home
            </div>

            <div class="logo-text">
                <div class="logo">
                    <img src="./Assets/Svg/motorcycle-svgrepo-com.svg" alt="Logo">
                </div>
                <h2>MotoRide</h2>
            </div>

            <h3>Welcome Back to MotoRide</h3>
            <p>Sign in to access your account and continue your motorcycle adventure.</p>

            <ul class="features">
                <li><span class="dot"></span> <b>Quick &amp; Easy Booking</b><br>Reserve your motorcycle in minutes</li>
                <li><span class="dot"></span> <b>Track Your Rentals</b><br>Manage your bookings in one place</li>
                <li><span class="dot"></span> <b>Exclusive Deals</b><br>Get special offers for members</li>
            </ul>

            <footer>© 2025 MotoRide. All rights reserved.</footer>
        </div>

        <!-- RIGHT SIDE LOGIN CARD -->
        <div class="right-panel">
            <div class="login-card">

                <h2>Login</h2>
                <p class="sub">Sign in to browse and rent motorcycles</p>

                <form action="./Authentication/login-handler.php" method="POST">

                    <label>Email Address</label>
                    <div class="input-box">
                        <input type="email" name="email" placeholder="you@example.com" required>
                    </div>

                    <label>Password</label>
                    <div class="input-box">
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>

                    <div class="links">
                        <label class="remember">
                            <input type="checkbox" name="remember"> Remember me
                        </label>
                        <label class="forgot">
                            <a href="">Forgot password?</a>
                        </label>
                    </div>

                    <button type="submit" class="login-btn">Sign In</button>

                    <div class="sign-up-link">
                        <label for="link">Don't have an account? <a href="signup.php">Sign up</a></label>
                    </div>

                </form>
            </div>
        </div>

    </div>


    <script>
        /* ── Dialog helpers ── */

        const ICONS = {
            error: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                   stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8"  x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>`,
            success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                    stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="10"/>
                  <polyline points="9 12 11 14 15 10"/>
                </svg>`
        };

        const TITLES = {
            error: 'Something went wrong',
            success: 'You\'re all set!'
        };

        function openDialog(type, message) {
            const overlay = document.getElementById('dialogOverlay');
            const card = document.getElementById('dialogCard');

            card.className = 'dialog-card is-' + type;
            document.getElementById('dialogIcon').innerHTML = ICONS[type];
            document.getElementById('dialogTitle').textContent = TITLES[type];
            document.getElementById('dialogBody').textContent = message;

            overlay.classList.add('show');
        }

        function closeDialog() {
            document.getElementById('dialogOverlay').classList.remove('show');
        }

        /* close on backdrop click */
        document.getElementById('dialogOverlay').addEventListener('click', function(e) {
            if (e.target === this) closeDialog();
        });

        /* close on Escape key */
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeDialog();
        });

        /* ── Auto-trigger from PHP session ── */
        <?php if (!empty($dialog_message)): ?>
            openDialog(
                <?= json_encode($dialog_type) ?>,
                <?= json_encode(htmlspecialchars_decode(strip_tags($dialog_message))) ?>
            );
        <?php endif; ?>
    </script>

</body>

</html>
