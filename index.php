<?php
/**
 * Login / Register Page
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in? Redirect
if (isset($_SESSION['user_id'])) {
    $redirect = isAdmin() ? 'admin' : 'polls';
    header('Location: ' . BASE_URL . '/' . $redirect);
    exit;
}

$pageTitle = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="Team-Grid — Football Team Management">
    <meta name="theme-color" content="#0a0e17">
    <title><?php echo $pageTitle . ' — ' . APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assets/lib/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="<?php echo rtrim(BASE_URL, '/\\'); ?>/assets/css/style.css">
    <style>
        body.login-page {
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(24px, 4vw, 64px) clamp(16px, 3vw, 48px);
            position: relative;
            overflow-x: hidden;
            box-sizing: border-box;
            background: var(--bg-primary);
        }

        /* Animated background - football field stripes & glows */
        .login-bg {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background: 
                radial-gradient(ellipse at 15% 50%, rgba(0, 230, 118, 0.08) 0%, transparent 55%),
                radial-gradient(ellipse at 85% 25%, rgba(68, 138, 255, 0.06) 0%, transparent 55%),
                radial-gradient(ellipse at 50% 85%, rgba(255, 215, 0, 0.04) 0%, transparent 60%),
                var(--bg-primary);
        }

        .login-bg::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: clamp(450px, 50vw, 900px);
            height: clamp(450px, 50vw, 900px);
            border: 1px solid rgba(0, 230, 118, 0.07);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            animation: fieldPulse 10s ease-in-out infinite;
        }

        .login-bg::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(0, 230, 118, 0.1), transparent);
        }

        @keyframes fieldPulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; }
            50% { transform: translate(-50%, -50%) scale(1.15); opacity: 1; }
        }

        /* Layout Container */
        .login-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 1180px;
            margin: 0 auto;
        }

        .login-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--spacing-xl);
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        /* Desktop & Big Screens */
        @media (min-width: 992px) {
            .login-layout {
                grid-template-columns: 1.15fr 0.85fr;
                gap: clamp(40px, 5vw, 76px);
                align-items: center;
            }
        }

        @media (min-width: 1400px) {
            .login-wrapper {
                max-width: 1260px;
            }
            .login-layout {
                grid-template-columns: 1.2fr 0.8fr;
                gap: 88px;
            }
        }

        /* Left Side: Brand Showcase */
        .login-brand-side {
            display: none;
            flex-direction: column;
            gap: var(--spacing-lg);
        }

        @media (min-width: 992px) {
            .login-brand-side {
                display: flex;
            }
        }

        .brand-badge-row {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .tm-logo-badge.large {
            width: 58px;
            height: 68px;
        }

        .tm-logo-badge.large .tm-logo-text {
            font-size: 20px;
        }

        .brand-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: rgba(0, 230, 118, 0.08);
            border: 1px solid rgba(0, 230, 118, 0.25);
            border-radius: 20px;
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
            color: var(--accent-green);
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background: var(--accent-green);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--accent-green);
            animation: dotPulse 2s infinite ease-in-out;
        }

        @keyframes dotPulse {
            0%, 100% { opacity: 0.4; transform: scale(0.9); }
            50% { opacity: 1; transform: scale(1.2); }
        }

        .brand-headline {
            font-size: clamp(2rem, 3.2vw, 3.2rem);
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.5px;
            color: var(--text-primary);
        }

        .brand-headline span {
            background: linear-gradient(135deg, var(--accent-green) 0%, #00e5ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-description {
            font-size: var(--font-size-base);
            color: var(--text-secondary);
            line-height: 1.6;
            max-width: 540px;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: var(--spacing-md);
            margin-top: var(--spacing-xs);
        }

        .feature-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            background: rgba(26, 31, 46, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: var(--radius-lg);
            backdrop-filter: blur(8px);
            transition: all var(--transition-normal);
        }

        .feature-card:hover {
            border-color: rgba(0, 230, 118, 0.3);
            background: rgba(34, 40, 64, 0.75);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
        }

        .feature-icon {
            font-size: 20px;
            padding: 8px;
            background: rgba(0, 230, 118, 0.1);
            border-radius: var(--radius-md);
            line-height: 1;
            flex-shrink: 0;
        }

        .feature-text h4 {
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-semibold);
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .feature-text p {
            font-size: 11px;
            color: var(--text-secondary);
            line-height: 1.35;
            margin: 0;
        }

        .brand-stats {
            display: flex;
            align-items: center;
            gap: var(--spacing-lg);
            padding: var(--spacing-md) var(--spacing-lg);
            background: rgba(17, 24, 39, 0.6);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            max-width: max-content;
            margin-top: var(--spacing-xs);
        }

        .stat-item {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-size: var(--font-size-md);
            font-weight: var(--font-weight-bold);
            color: var(--accent-green);
            line-height: 1.2;
        }

        .stat-label {
            font-size: 11px;
            color: var(--text-muted);
        }

        .stat-divider {
            width: 1px;
            height: 28px;
            background: var(--border-color);
        }

        /* Right Side: Auth Card */
        .login-card-side {
            width: 100%;
            max-width: 460px;
            margin: 0 auto;
        }

        @media (min-width: 992px) {
            .login-card-side {
                max-width: 480px;
            }
        }

        .mobile-brand-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: var(--spacing-lg);
        }

        @media (min-width: 992px) {
            .mobile-brand-header {
                display: none;
            }
        }

        .mobile-brand-header h2 {
            font-size: var(--font-size-2xl);
            font-weight: var(--font-weight-bold);
            margin-top: var(--spacing-sm);
            margin-bottom: 4px;
            background: linear-gradient(135deg, var(--text-primary), var(--accent-green));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .mobile-brand-header p {
            color: var(--text-secondary);
            font-size: var(--font-size-sm);
        }

        .login-card {
            background: rgba(26, 31, 46, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius-xl);
            padding: clamp(24px, 3.5vw, 36px);
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.55), 0 0 32px rgba(0, 230, 118, 0.06);
            position: relative;
        }

        .card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--spacing-lg);
        }

        .card-title {
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
            line-height: 1.2;
        }

        .card-subtitle {
            font-size: var(--font-size-xs);
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .card-status-badge {
            padding: 4px 10px;
            background: rgba(0, 230, 118, 0.12);
            border: 1px solid rgba(0, 230, 118, 0.3);
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            color: var(--accent-green);
            white-space: nowrap;
        }

        .login-tabs {
            display: flex;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 4px;
            margin-bottom: var(--spacing-lg);
            gap: 4px;
        }

        .login-tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-semibold);
            color: var(--text-secondary);
            background: none;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all var(--transition-fast);
            font-family: var(--font-family);
        }

        .login-tab.active {
            background: var(--accent-green);
            color: var(--text-inverse);
            box-shadow: 0 2px 10px rgba(0, 230, 118, 0.35);
        }

        .login-form {
            display: none;
        }

        .login-form.active {
            display: block;
            animation: slideUp 0.3s ease;
        }

        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            font-size: 16px;
            color: var(--text-muted);
            pointer-events: none;
            z-index: 2;
        }

        .input-with-icon .form-input {
            padding-left: 42px;
        }

        .login-footer {
            text-align: center;
            margin-top: var(--spacing-lg);
            font-size: 11px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .login-footer .footer-icon {
            display: inline-block;
            margin-right: 4px;
        }

        /* Floating football particles */
        .particle {
            position: fixed;
            font-size: 20px;
            opacity: 0.06;
            z-index: 0;
            animation: floatParticle linear infinite;
            pointer-events: none;
        }

        @keyframes floatParticle {
            0% { transform: translateY(100vh) rotate(0deg); }
            100% { transform: translateY(-100px) rotate(360deg); }
        }
    </style>
</head>
<body class="login-page">
    <!-- Background -->
    <div class="login-bg"></div>

    <!-- Floating particles -->
    <div class="particle" style="left:8%;animation-duration:18s;animation-delay:0s;">⚽</div>
    <div class="particle" style="left:26%;animation-duration:22s;animation-delay:3s;font-size:16px;">⚽</div>
    <div class="particle" style="left:50%;animation-duration:20s;animation-delay:6s;">⚽</div>
    <div class="particle" style="left:72%;animation-duration:25s;animation-delay:2s;font-size:14px;">⚽</div>
    <div class="particle" style="left:92%;animation-duration:19s;animation-delay:5s;">⚽</div>

    <main class="login-wrapper">
        <div class="login-layout">
            <!-- Left Side: Interactive Branding Showcase (Desktop / Big Screen) -->
            <div class="login-brand-side">
                <div class="brand-badge-row">
                    <div class="tm-logo-badge large">
                        <div class="tm-logo-shield"></div>
                        <span class="tm-logo-text">TG</span>
                    </div>
                    <div class="brand-pill">
                        <span class="pulse-dot"></span> Matchday Operations
                    </div>
                </div>

                <h1 class="brand-headline">
                    Easiest Way to manage your Team<br>
                    <span>Team Grid</span>
                </h1>
                
                <p class="brand-description">
                    The next-gen football squad hub. Automated fair team selection, real-time matchday polls, tactical pitch formations, and seamless turf fee splitting.
                </p>

                <!-- Feature highlights cards -->
                <div class="feature-grid">
                    <div class="feature-card">
                        <div class="feature-icon">⚡</div>
                        <div class="feature-text">
                            <h4>Smart Team Drafts</h4>
                            <p>Balanced captain picks & fair algorithm drafts</p>
                        </div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <div class="feature-text">
                            <h4>Matchday Polls</h4>
                            <p>Live attendance voting & auto-deadline closing</p>
                        </div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💰</div>
                        <div class="feature-text">
                            <h4>Turf Cost Splitting</h4>
                            <p>Instant per-player math & paid/pending tracking</p>
                        </div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📋</div>
                        <div class="feature-text">
                            <h4>Tactical Lineups</h4>
                            <p>Interactive 6v6, 7v7, 8v8 formation pitch board</p>
                        </div>
                    </div>
                </div>

                <!-- Spec / Security Stats -->
                <div class="brand-stats">
                    <div class="stat-item">
                        <span class="stat-number">Easy</span>
                        <span class="stat-label">to Use</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <span class="stat-number">Fast</span>
                        <span class="stat-label">Speed</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <span class="stat-number">Real-Time</span>
                        <span class="stat-label">Notifications</span>
                    </div>
                </div>
            </div>

            <!-- Right Side: Auth Card -->
            <div class="login-card-side">
                <!-- Mobile Only Header (hidden on large screen) -->
                <div class="mobile-brand-header">
                    <div class="tm-logo-badge">
                        <div class="tm-logo-shield"></div>
                        <span class="tm-logo-text">TG</span>
                    </div>
                    <h2>Team-Grid</h2>
                    <p>Team control center & matchday tools</p>
                </div>

                <!-- Main Glass Card -->
                <div class="login-card">
                    <div class="card-head">
                        <div>
                            <h2 class="card-title">Team Access</h2>
                            <p class="card-subtitle">Sign in or register your player profile</p>
                        </div>
                        <span class="card-status-badge">Match Ready</span>
                    </div>

                    <div class="login-tabs">
                        <button class="login-tab active" data-tab="login" id="tabLogin">Sign In</button>
                        <button class="login-tab" data-tab="register" id="tabRegister">Register</button>
                    </div>

                    <!-- Login Form -->
                    <form class="login-form active" id="loginForm">
                        <div class="form-group">
                            <label class="form-label" for="loginName">First Name <span class="required">*</span></label>
                            <div class="input-with-icon">
                                <span class="input-icon">⚽</span>
                                <input type="text" class="form-input" id="loginName" placeholder="Enter your first name (e.g. Kalash)" required autocomplete="given-name">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="loginDob">Date of Birth <span class="required">*</span></label>
                            <div class="input-with-icon">
                                <span class="input-icon">📅</span>
                                <input type="text" class="form-input dob-picker-input" id="loginDob" placeholder="DD-MM-YYYY" required autocomplete="bday">
                                <button type="button" class="dob-calendar-toggle" aria-label="Pick date of birth" tabindex="-1">📆</button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg btn-block" id="loginBtn">
                            Login
                        </button>
                    </form>

                    <!-- Register Form -->
                    <form class="login-form" id="registerForm">
                        <div class="form-group">
                            <label class="form-label" for="regFirstName">First Name <span class="required">*</span></label>
                            <div class="input-with-icon">
                                <span class="input-icon">⚽</span>
                                <input type="text" class="form-input" id="regFirstName" placeholder="Enter your first name" required autocomplete="given-name">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="regLastName">Last Name</label>
                            <div class="input-with-icon">
                                <span class="input-icon">🏷️</span>
                                <input type="text" class="form-input" id="regLastName" placeholder="Enter your last name" autocomplete="family-name">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="regDob">Date of Birth <span class="required">*</span></label>
                            <div class="input-with-icon">
                                <span class="input-icon">📅</span>
                                <input type="text" class="form-input dob-picker-input" id="regDob" placeholder="DD-MM-YYYY" required autocomplete="bday">
                                <button type="button" class="dob-calendar-toggle" aria-label="Pick date of birth" tabindex="-1">📆</button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg btn-block" id="regBtn">
                            🚀 Create Player Profile
                        </button>
                    </form>

                    <div class="login-footer">
                        <span class="footer-icon">🔒</span> No password hassle
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo rtrim(BASE_URL, '/\\'); ?>/assets/lib/sweetalert2/sweetalert2.all.min.js"></script>
    <script src="<?php echo rtrim(BASE_URL, '/\\'); ?>/assets/js/dob-picker.js"></script>
    <script>
        const BASE = '<?php echo rtrim(BASE_URL, "/\\"); ?>';

        // Tab switching
        document.querySelectorAll('.login-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.login-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.login-form').forEach(f => f.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab === 'login' ? 'loginForm' : 'registerForm').classList.add('active');
            });
        });

        // Login
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = document.getElementById('loginName').value.trim();
            const dob = document.getElementById('loginDob').value;
            if (!name || !dob) return;

            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.textContent = 'Logging in...';

            try {
                const res = await fetch(`${BASE}/api/auth.php?action=login`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ first_name: name, dob: dob })
                });
                const data = await res.json();

                if (data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Welcome!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false,
                    });
                    window.location.href = `${BASE}/${data.redirect}`;
                } else {
                    Swal.fire({ icon: 'error', title: 'Oops!', text: data.message });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong' });
            }
            btn.disabled = false;
            btn.textContent = '⚡ Login';
        });

        // Register
        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const firstName = document.getElementById('regFirstName').value.trim();
            const lastName = document.getElementById('regLastName').value.trim();
            const dob = document.getElementById('regDob').value;
            if (!firstName || !dob) return;

            const btn = document.getElementById('regBtn');
            btn.disabled = true;
            btn.textContent = 'Creating...';

            try {
                const res = await fetch(`${BASE}/api/auth.php?action=register`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ first_name: firstName, last_name: lastName, dob: dob })
                });
                const data = await res.json();

                if (data.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: '🎉 Welcome!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false,
                    });
                    window.location.href = `${BASE}/${data.redirect}`;
                } else {
                    Swal.fire({ icon: 'error', title: 'Oops!', text: data.message });
                }
            } catch (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong' });
            }
            btn.disabled = false;
            btn.textContent = '🚀 Create Account';
        });
    </script>
</body>
</html>
