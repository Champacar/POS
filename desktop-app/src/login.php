<?php
/**
 * Login - Modern Pharmacy POS Authentication
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
setSecurityHeaders();

$settings = null;
try {
    $settings = getShopSettings();
} catch (Exception $e) {
    // Ignore
}
$shopName = $settings['shop_name'] ?? 'ລະບົບຂາຍຢາດີ';
$shopNameEn = $settings['shop_name_en'] ?? 'POS Pharmacy';
$shopLogo = $settings['logo'] ?? null;
$shopFavicon = $settings['favicon'] ?? null;

// If already logged in, redirect to index
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken()) {
        $error = 'ເກີດຂໍ້ຜິດພາດ CSRF. ກະລຸນາ refresh ໜ້າ ແລ້ວລອງໃໝ່.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Rate limiting check
        $rateCheck = checkRateLimit('login_' . $username);
        if (!$rateCheck['allowed']) {
            $error = $rateCheck['message'];
        } elseif (empty($username) || empty($password)) {
            $error = 'ກະລຸນາປ້ອນຊື່ຜູ້ໃຊ້ ແລະ ລະຫັດຜ່ານ';
        } else {
            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    if ($user['is_active']) {
                        // Update last login
                        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                        
                        // Regenerate session ID to prevent session fixation
                        regenerateSession();
                        
                        // Reset rate limit
                        resetRateLimit('login_' . $username);
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['login_time'] = time();

                        // Load custom permissions if set
                        $userPerms = [];
                        if (!empty($user['permissions'])) {
                            $decoded = json_decode($user['permissions'], true);
                            if (is_array($decoded)) {
                                $userPerms = $decoded;
                            }
                        }
                        $_SESSION['permissions'] = $userPerms;
                        
                        header('Location: ' . BASE_URL . ($user['role'] === 'cashier' ? 'pos.php' : 'index.php'));
                        exit;
                    } else {
                        $error = 'ບັນຊີຂອງທ່ານຖືກລະງັບການນຳໃຊ້';
                    }
                } else {
                    // Record failed attempt
                    recordFailedAttempt('login_' . $username);
                    $error = 'ຊື່ຜູ້ໃຊ້ ຫຼື ລະຫັດຜ່ານບໍ່ຖືກຕ້ອງ';
                }
            } catch (Exception $e) {
                $error = safeErrorMessage($e);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="lo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ເຂົ້າສູ່ລະບົບ | <?= htmlspecialchars($shopName) ?></title>
    <meta name="description" content="<?= htmlspecialchars($shopName) ?> - ລະບົບຄຸ້ມຄອງການຂາຍໜ້າຮ້ານ, ຕັດສະຕ໋ອກ ແລະ ຈັດການວັນໝົດອາຍຸຢາສຳລັບຮ້ານຂາຍຢາ">
    <?php if (!empty($shopFavicon) && file_exists(__DIR__ . '/assets/images/' . $shopFavicon)): ?>
        <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($shopFavicon) ?>">
    <?php elseif (!empty($shopLogo) && file_exists(__DIR__ . '/assets/images/' . $shopLogo)): ?>
        <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($shopLogo) ?>">
    <?php else: ?>
        <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/favicon.png">
        <link rel="shortcut icon" href="<?= BASE_URL ?>assets/images/favicon.ico">
    <?php endif; ?>
    
    <!-- Local Font & Icons (Offline Bundle) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fonts/noto-sans-lao.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100vh;
            overflow: hidden;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        html::-webkit-scrollbar, body::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }

        body {
            font-family: 'Noto Sans Lao', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at 10% 20%, #0d3816 0%, #15471e 40%, #0a2910 90%);
            position: relative;
            padding: 20px 16px;
        }

        /* Ambient Background Elements */
        .ambient-glow {
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(76, 175, 80, 0.18) 0%, rgba(46, 125, 50, 0.05) 50%, transparent 70%);
            filter: blur(60px);
            pointer-events: none;
            z-index: 1;
        }

        .glow-1 {
            top: -100px;
            right: -100px;
            animation: pulseGlow 12s ease-in-out infinite alternate;
        }

        .glow-2 {
            bottom: -150px;
            left: -100px;
            background: radial-gradient(circle, rgba(129, 199, 132, 0.15) 0%, rgba(27, 94, 32, 0.04) 60%, transparent 70%);
            animation: pulseGlow 15s ease-in-out infinite alternate-reverse;
        }

        @keyframes pulseGlow {
            0% { transform: scale(1) translate(0, 0); opacity: 0.6; }
            50% { transform: scale(1.15) translate(20px, 30px); opacity: 0.9; }
            100% { transform: scale(0.95) translate(-20px, -10px); opacity: 0.6; }
        }

        /* Master Modern Split Card */
        .login-master-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 960px;
            min-height: 560px;
            background: #ffffff;
            border-radius: 28px;
            box-shadow: 0 30px 70px -15px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.15);
            display: grid;
            grid-template-columns: 1.15fr 1fr;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        /* Left Showcase Panel */
        .showcase-panel {
            background: linear-gradient(145deg, #103e18 0%, #1b5e20 50%, #2e7d32 100%);
            padding: 48px 42px;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .showcase-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: 
                radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px),
                radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
            background-size: 24px 24px;
            background-position: 0 0, 12px 12px;
            opacity: 0.6;
            pointer-events: none;
        }

        .brand-header {
            position: relative;
            z-index: 2;
        }

        .brand-logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 16px 8px 10px;
            border-radius: 9999px;
            margin-bottom: 28px;
        }

        .logo-symbol {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffffff 0%, #c8e6c9 100%);
            color: #1b5e20;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .brand-badge-text {
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            color: #e8f5e9;
            text-transform: uppercase;
        }

        .showcase-title {
            font-size: 2.1rem;
            font-weight: 800;
            line-height: 1.25;
            color: #ffffff;
            margin-bottom: 12px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        }

        .showcase-desc {
            font-size: 0.95rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.85);
            max-width: 380px;
        }

        /* Feature Pillars */
        .feature-list {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin: 32px 0;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .feature-item:hover {
            transform: translateX(4px);
            background: rgba(255, 255, 255, 0.14);
        }

        .feature-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.18);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .feature-text h4 {
            font-size: 0.92rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 2px;
        }

        .feature-text p {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.78);
            line-height: 1.4;
        }

        /* Showcase Footer */
        .showcase-footer {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #a5d6a7;
            font-weight: 600;
        }

        /* Right Form Panel */
        .form-panel {
            padding: 48px 44px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .form-header {
            margin-bottom: 30px;
        }

        .form-welcome-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #2e7d32;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .form-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .form-subtitle {
            font-size: 0.88rem;
            color: #64748b;
            line-height: 1.5;
        }

        /* Alert Styling */
        .login-alert {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            background: #fef2f2;
            border: 1px solid #fee2e2;
            color: #b91c1c;
            font-size: 0.88rem;
            margin-bottom: 22px;
            animation: shakeAlert 0.4s ease-in-out;
        }

        @keyframes shakeAlert {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        /* Modern Form Groups & Floating Inputs */
        .modern-form-group {
            margin-bottom: 22px;
            text-align: left;
        }

        .modern-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.88rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .modern-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-lead-icon {
            position: absolute;
            left: 16px;
            color: #94a3b8;
            font-size: 1rem;
            pointer-events: none;
            transition: color 0.2s ease;
            z-index: 2;
        }

        .modern-input {
            width: 100%;
            height: 50px;
            padding: 0 46px 0 44px;
            font-size: 0.95rem;
            font-family: inherit;
            color: #1e293b;
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            transition: all 0.2s ease;
            outline: none;
        }

        .modern-input::placeholder {
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .modern-input:focus {
            background: #ffffff;
            border-color: #2e7d32;
            box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.12);
        }

        .modern-input:focus + .input-lead-icon,
        .modern-input-wrapper:focus-within .input-lead-icon {
            color: #2e7d32;
        }

        .modern-input.is-invalid {
            border-color: #ef4444 !important;
            background: #fff8f8 !important;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12) !important;
        }

        /* Password Toggle Button */
        .btn-toggle-eye {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 6px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s ease;
            z-index: 2;
        }

        .btn-toggle-eye:hover {
            color: #2e7d32;
            background: #f1f5f9;
        }

        /* Submit Button */
        .btn-login-modern {
            width: 100%;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 25px -5px rgba(46, 125, 50, 0.45);
            transition: all 0.25s ease;
            margin-top: 10px;
        }

        .btn-login-modern:hover {
            background: linear-gradient(135deg, #338a37 0%, #17521c 100%);
            box-shadow: 0 14px 28px -4px rgba(46, 125, 50, 0.55);
            transform: translateY(-2px);
        }

        .btn-login-modern:active {
            transform: translateY(0);
            box-shadow: 0 6px 15px -3px rgba(46, 125, 50, 0.4);
        }

        .btn-login-modern i.arrow-icon {
            transition: transform 0.2s ease;
        }

        .btn-login-modern:hover i.arrow-icon {
            transform: translateX(4px);
        }

        .btn-login-modern .login-spinner {
            display: none;
        }

        .btn-login-modern.loading .login-spinner {
            display: inline-block;
            animation: spin 0.9s infinite linear;
        }

        .btn-login-modern.loading span,
        .btn-login-modern.loading i.arrow-icon {
            display: none;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Form Footer */
        .form-footer {
            margin-top: 30px;
            padding-top: 18px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.78rem;
            color: #94a3b8;
        }

        .offline-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #15803d;
            background: #f0fdf4;
            padding: 4px 10px;
            border-radius: 9999px;
            font-weight: 600;
            border: 1px solid #dcfce7;
        }

        /* Responsive Breakpoints */
        @media (max-width: 860px), (max-height: 680px) {
            html, body {
                height: auto !important;
                min-height: 100vh !important;
                overflow-y: auto !important;
            }

            .login-master-card {
                grid-template-columns: 1fr;
                max-width: 480px;
                min-height: auto;
                border-radius: 22px;
            }

            .showcase-panel {
                padding: 32px 28px;
            }

            .showcase-title {
                font-size: 1.6rem;
            }

            .feature-list {
                display: none; /* Hide heavy features on mobile for faster login */
            }

            .form-panel {
                padding: 36px 28px;
            }
        }
    </style>
</head>
<body>

    <!-- Ambient Glow Orbs -->
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <!-- Master Modern Split Card -->
    <div class="login-master-card">
        
        <!-- Left Showcase / Branding Panel -->
        <div class="showcase-panel">
            <div class="brand-header">
                <div class="brand-logo-badge">
                    <div class="logo-symbol">
                        <?php if (!empty($shopLogo) && file_exists(__DIR__ . '/assets/images/' . $shopLogo)): ?>
                            <img src="<?= BASE_URL ?>assets/images/<?= htmlspecialchars($shopLogo) ?>" alt="Logo" style="width:100%;height:100%;object-fit:contain;border-radius:8px;">
                        <?php else: ?>
                            <i class="fas fa-plus"></i>
                        <?php endif; ?>
                    </div>
                    <span class="brand-badge-text"><?= htmlspecialchars($shopNameEn) ?></span>
                </div>
                <h1 class="showcase-title"><?= htmlspecialchars($shopName) ?></h1>
                <p class="showcase-desc">ລະບົບຄຸ້ມຄອງການຂາຍໜ້າຮ້ານ ແລະ ບໍລິຫານສາງຢາຄົບວົງຈອນ ອອກແບບສະເພາະສຳລັບຮ້ານຂາຍຢາທີ່ຕ້ອງການຄວາມວ່ອງໄວ ແລະ ແມ່ນຍຳ.</p>
            </div>

            <!-- Features Showcase -->
            <div class="feature-list">
                <div class="feature-item">
                    <div class="feature-icon-box">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div class="feature-text">
                        <h4>ຂາຍໄວ & ສະແກນບາໂຄດທັນໃຈ</h4>
                        <p>ຄິດໄລ່ເງິນທອນ ແລະ ພິມບິນຮັບເງິນຢ່າງວ່ອງໄວ</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon-box">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="feature-text">
                        <h4>ຄວບຄຸມ Lot & ວັນໝົດອາຍຸ (FEFO)</h4>
                        <p>ຕັດສະຕ໋ອກຕາມວັນໝົດອາຍຸ ປ້ອງກັນຢາຕົກຄ້າງ</p>
                    </div>
                </div>

                <div class="feature-item">
                    <div class="feature-icon-box">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-text">
                        <h4>ຮອງຮັບ Offline 100%</h4>
                        <p>ເຮັດວຽກໄດ້ຢ່າງສະຖຽນ ຂໍ້ມູນບໍ່ສູນຫາຍເຖິງບໍ່ມີເນັດ</p>
                    </div>
                </div>
            </div>

            <!-- Showcase Footer -->
            <div class="showcase-footer">
                <span class="security-badge">
                    <i class="fas fa-lock"></i> ລະບົບປອດໄພລະດັບສູງ
                </span>
                <span>v1.0.0</span>
            </div>
        </div>

        <!-- Right Login Form Panel -->
        <div class="form-panel">
            <div>
                <div class="form-header">
                    <div class="form-welcome-tag">
                        <i class="fas fa-shield-check"></i> ຍິນດີຕ້ອນຮັບ
                    </div>
                    <h2 class="form-title">ເຂົ້າສູ່ລະບົບ</h2>
                    <p class="form-subtitle">ປ້ອນຊື່ຜູ້ໃຊ້ ແລະ ລະຫັດຜ່ານຂອງທ່ານເພື່ອເລີ່ມຕົ້ນງານ</p>
                </div>

                <?php if ($error): ?>
                <div class="login-alert">
                    <i class="fas fa-exclamation-circle" style="font-size:1.1rem; flex-shrink:0;"></i>
                    <span><?= sanitize($error) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="" onsubmit="onSubmitLogin(event)">
                    <?= csrfField() ?>
                    
                    <!-- Username Input -->
                    <div class="modern-form-group">
                        <label class="modern-label" for="username">
                            <span>ຊື່ຜູ້ໃຊ້</span>
                        </label>
                        <div class="modern-input-wrapper">
                            <i class="fas fa-user input-lead-icon"></i>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                class="modern-input <?= $error ? 'is-invalid' : '' ?>" 
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                                required 
                                autofocus 
                                placeholder="ປ້ອນຊື່ຜູ້ໃຊ້ (Username)">
                        </div>
                    </div>

                    <!-- Password Input -->
                    <div class="modern-form-group">
                        <label class="modern-label" for="password">
                            <span>ລະຫັດຜ່ານ</span>
                        </label>
                        <div class="modern-input-wrapper">
                            <i class="fas fa-lock input-lead-icon"></i>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="modern-input <?= $error ? 'is-invalid' : '' ?>" 
                                required 
                                placeholder="ປ້ອນລະຫັດຜ່ານ (Password)">
                            <button 
                                type="button" 
                                class="btn-toggle-eye" 
                                onclick="togglePasswordVisibility()" 
                                aria-label="ສະແດງ/ເຊື່ອງລະຫັດຜ່ານ" 
                                tabindex="-1">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="btnLogin" class="btn-login-modern">
                        <span>ເຂົ້າສູ່ລະບົບ</span>
                        <i class="fas fa-arrow-right arrow-icon"></i>
                        <i class="fas fa-spinner login-spinner"></i>
                    </button>
                </form>
            </div>

            <!-- Form Footer -->
            <div class="form-footer">
                <span class="offline-status-pill">
                    <i class="fas fa-check-circle"></i> ໂໝດ Offline ພ້ອມໃຊ້ງານ
                </span>
                <span>&copy; <?= date('Y') ?> <?= htmlspecialchars($shopName) ?></span>
            </div>
        </div>

    </div>

    <script>
        function togglePasswordVisibility() {
            const pwInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            if (pwInput.type === 'password') {
                pwInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                pwInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }

        function onSubmitLogin(e) {
            const btn = document.getElementById('btnLogin');
            btn.classList.add('loading');
            setTimeout(() => {
                btn.disabled = true;
            }, 10);
        }

        window.addEventListener('pageshow', function() {
            const btn = document.getElementById('btnLogin');
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('loading');
            }
        });
    </script>

</body>
</html>