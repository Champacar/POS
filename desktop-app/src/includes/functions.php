<?php
/**
 * Helper Functions
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    
    // Set secure cookie flag if using HTTPS
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// ============================================
// Security Functions
// ============================================

/**
 * Set security headers for all responses
 */
function setSecurityHeaders() {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header_remove('X-Powered-By');
    }
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Get CSRF hidden input field HTML
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
}

/**
 * Verify CSRF token from request
 */
function verifyCSRFToken($token = null) {
    if ($token === null) {
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Require valid CSRF token or send error response
 */
function requireCSRFToken() {
    if (!verifyCSRFToken()) {
        if (isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'CSRF token ບໍ່ຖືກຕ້ອງ. ກະລຸນາ refresh ໜ້າ.']);
            exit;
        } else {
            http_response_code(403);
            die('CSRF token ບໍ່ຖືກຕ້ອງ. ກະລຸນາ refresh ໜ້າ ແລ້ວລອງໃໝ່.');
        }
    }
}

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Rate limiting for login attempts (session-based, works on all hosting)
 */
function checkRateLimit($identifier) {
    $key = 'rate_limit_' . md5($identifier);
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['attempts' => 0, 'last_attempt' => $now, 'locked_until' => 0];
    }
    
    $data = &$_SESSION[$key];
    
    // Check if currently locked out
    if ($data['locked_until'] > $now) {
        $remainingMinutes = ceil(($data['locked_until'] - $now) / 60);
        return [
            'allowed' => false,
            'message' => "ທ່ານລອງເຂົ້າລະບົບຜິດຫຼາຍເກີນໄປ. ກະລຸນາລໍຖ້າ {$remainingMinutes} ນາທີ.",
            'remaining_seconds' => $data['locked_until'] - $now,
        ];
    }
    
    // Reset attempts if last attempt was more than lockout period ago
    if (($now - $data['last_attempt']) > (RATE_LIMIT_LOCKOUT_MINUTES * 60)) {
        $data['attempts'] = 0;
        $data['locked_until'] = 0;
    }
    
    return ['allowed' => true, 'attempts' => $data['attempts']];
}

/**
 * Record a failed login attempt
 */
function recordFailedAttempt($identifier) {
    $key = 'rate_limit_' . md5($identifier);
    $now = time();
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['attempts' => 0, 'last_attempt' => $now, 'locked_until' => 0];
    }
    
    $_SESSION[$key]['attempts']++;
    $_SESSION[$key]['last_attempt'] = $now;
    
    if ($_SESSION[$key]['attempts'] >= RATE_LIMIT_MAX_ATTEMPTS) {
        $_SESSION[$key]['locked_until'] = $now + (RATE_LIMIT_LOCKOUT_MINUTES * 60);
    }
}

/**
 * Reset rate limit after successful login
 */
function resetRateLimit($identifier) {
    $key = 'rate_limit_' . md5($identifier);
    unset($_SESSION[$key]);
}

/**
 * Regenerate session ID (prevent session fixation)
 */
function regenerateSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Require API authentication - returns JSON error if not logged in
 */
function requireAPILogin() {
    if (!isLoggedIn()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'ກະລຸນາເຂົ້າສູ່ລະບົບກ່ອນ']);
        exit;
    }
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin' && ($_SESSION['role'] ?? '') !== 'developer') {
        if (isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດເຂົ້າເຖິງ']);
            exit;
        } else {
            http_response_code(403);
            die('ທ່ານບໍ່ມີສິດເຂົ້າເຖິງ');
        }
    }
}

/**
 * Require developer role
 */
function requireDeveloper() {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'developer') {
        if (isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດເຂົ້າເຖິງ (Developer only)']);
            exit;
        } else {
            http_response_code(403);
            die('ທ່ານບໍ່ມີສິດເຂົ້າເຖິງ (Developer only)');
        }
    }
}

/**
 * Check if current user has any of the given roles
 * Developer always matches
 */
function hasRole($roles) {
    $currentRole = $_SESSION['role'] ?? '';
    if ($currentRole === 'developer') {
        return true;
    }
    if (is_string($roles)) {
        return $currentRole === $roles;
    }
    if (is_array($roles)) {
        return in_array($currentRole, $roles, true);
    }
    return false;
}

/**
 * Require specific roles or terminate with 403
 */
function requireRoles(array $allowedRoles) {
    requireLogin();
    $currentRole = $_SESSION['role'] ?? '';
    if ($currentRole === 'developer') {
        return;
    }
    if (!in_array($currentRole, $allowedRoles, true)) {
        if (isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'ທ່ານບໍ່ມີສິດເຂົ້າເຖິງໜ້ານີ້']);
            exit;
        } else {
            http_response_code(403);
            die('ທ່ານບໍ່ມີສິດເຂົ້າເຖິງໜ້ານີ້ (Access Denied)');
        }
    }
}

/**
 * Default permissions map for each role
 */
function getDefaultPermissionsByRole($role) {
    switch ($role) {
        case 'admin':
        case 'developer':
            return [
                'can_view_profit' => true,
                'can_manage_stock' => true,
                'can_manage_products' => true,
                'can_manage_po' => true,
                'can_delete_sales' => true,
                'can_give_discount' => true,
                'can_view_reports' => true,
            ];
        case 'manager':
            return [
                'can_view_profit' => true,
                'can_manage_stock' => true,
                'can_manage_products' => true,
                'can_manage_po' => true,
                'can_delete_sales' => true,
                'can_give_discount' => true,
                'can_view_reports' => true,
            ];
        case 'pharmacist':
            return [
                'can_view_profit' => false,
                'can_manage_stock' => true,
                'can_manage_products' => true,
                'can_manage_po' => true,
                'can_delete_sales' => false,
                'can_give_discount' => true,
                'can_view_reports' => true,
            ];
        case 'cashier':
        default:
            return [
                'can_view_profit' => false,
                'can_manage_stock' => false,
                'can_manage_products' => false,
                'can_manage_po' => false,
                'can_delete_sales' => false,
                'can_give_discount' => false,
                'can_view_reports' => false,
            ];
    }
}

/**
 * Check if the currently logged-in user has a specific permission
 */
function hasPermission($permKey) {
    $currentRole = $_SESSION['role'] ?? '';
    if (in_array($currentRole, ['admin', 'developer'], true)) {
        return true;
    }
    
    // Check if session has custom permissions overrides
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        if (array_key_exists($permKey, $_SESSION['permissions'])) {
            return (bool)$_SESSION['permissions'][$permKey];
        }
    }
    
    $defaults = getDefaultPermissionsByRole($currentRole);
    return !empty($defaults[$permKey]);
}

/**
 * Require a specific permission or terminate with 403
 */
function requirePermission($permKey) {
    requireLogin();
    if (!hasPermission($permKey)) {
        if (isAjaxRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => "ທ່ານບໍ່ມີສິດດຳເນີນການນີ້ ($permKey)"]);
            exit;
        } else {
            http_response_code(403);
            die("ທ່ານບໍ່ມີສິດດຳເນີນການນີ້ (Access Denied: $permKey)");
        }
    }
}

/**
 * Shortcut to check profit & loss report viewing permission
 */
function canViewProfitLoss() {
    return hasPermission('can_view_profit');
}

/**
 * Get Lao label for user role
 */
function getRoleLabel($role) {
    $labels = [
        'admin' => 'ຜູ້ດູແລລະບົບ',
        'pharmacist' => 'ເພສັດຊະກອນ',
        'manager' => 'ຜູ້ຈັດການ',
        'cashier' => 'ພະນັກງານຂາຍ',
        'developer' => 'ນັກພັດທະນາ',
    ];
    return $labels[$role] ?? $role;
}

/**
 * Get badge CSS class for user role
 */
function getRoleBadgeClass($role) {
    switch ($role) {
        case 'admin':
            return 'badge-danger';
        case 'pharmacist':
            return 'badge-success';
        case 'manager':
            return 'badge-warning';
        case 'developer':
            return 'badge-dark';
        case 'cashier':
        default:
            return 'badge-info';
    }
}

/**
 * Safe error message for API responses (hide details in production)
 */
function safeErrorMessage($exception) {
    if (APP_ENV === 'development') {
        return $exception->getMessage();
    }
    error_log("Error: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
    return 'ເກີດຂໍ້ຜິດພາດ. ກະລຸນາລອງໃໝ່ ຫຼື ຕິດຕໍ່ຜູ້ດູແລລະບົບ.';
}

/**
 * Validate integer input
 */
function validateInt($value, $min = null, $max = null) {
    $value = filter_var($value, FILTER_VALIDATE_INT);
    if ($value === false) return false;
    if ($min !== null && $value < $min) return false;
    if ($max !== null && $value > $max) return false;
    return $value;
}

/**
 * Validate date format (Y-m-d)
 */
function validateDate($date) {
    if (empty($date)) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Validate value is in allowed list
 */
function validateEnum($value, array $allowed) {
    return in_array($value, $allowed, true);
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login to access page
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

/**
 * Get current logged-in user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? 1;
}

/**
 * Format number as Lao currency (ກີບ)
 */
function formatCurrency($amount) {
    return number_format($amount, 0, '.', ',');
}

/**
 * Format date to Lao-friendly format
 */
function formatDate($date) {
    if (empty($date)) return '-';
    return date('d/m/Y', strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime($datetime) {
    if (empty($datetime)) return '-';
    return date('d/m/Y H:i', strtotime($datetime));
}

/**
 * Generate unique invoice number (with retry for race condition safety)
 */
function generateInvoiceNumber() {
    $prefix = 'INV';
    $date = date('Ymd');
    $db = getDB();
    
    // Retry up to 5 times to handle race conditions
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE()");
        $row = $stmt->fetch();
        $seq = str_pad(($row['count'] + 1 + $attempt), 4, '0', STR_PAD_LEFT);
        $invoiceNumber = $prefix . $date . $seq;
        
        // Check if this invoice number already exists
        $check = $db->prepare("SELECT COUNT(*) as cnt FROM sales WHERE invoice_number = ?");
        $check->execute([$invoiceNumber]);
        if ($check->fetch()['cnt'] == 0) {
            return $invoiceNumber;
        }
    }
    
    // Fallback: use microtime for uniqueness
    return $prefix . $date . substr(str_replace('.', '', microtime(true)), -4);
}

/**
 * Generate unique official Tax Invoice number (e.g. TAX-20261109-0001)
 */
function generateTaxInvoiceNumber() {
    $db = getDB();
    $settings = getShopSettings();
    $prefix = !empty($settings['tax_invoice_prefix']) ? $settings['tax_invoice_prefix'] : 'TAX-';
    $date = date('Ymd');
    
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM sales WHERE is_tax_invoice = 1 AND DATE(created_at) = CURDATE()");
        $row = $stmt->fetch();
        $seq = str_pad(($row['count'] + 1 + $attempt), 4, '0', STR_PAD_LEFT);
        $taxInvNumber = $prefix . $date . '-' . $seq;
        
        $check = $db->prepare("SELECT COUNT(*) as cnt FROM sales WHERE tax_invoice_number = ?");
        $check->execute([$taxInvNumber]);
        if ($check->fetch()['cnt'] == 0) {
            return $taxInvNumber;
        }
    }
    return $prefix . $date . '-' . substr(str_replace('.', '', microtime(true)), -4);
}

/**
 * Convert number (LAK) to Lao text words for official invoices/receipts
 */
function numberToLaoWords($number) {
    $number = (float)$number;
    if ($number == 0) {
        return 'ສູນກີບຖ້ວນ';
    }

    $units = ['', 'ໜຶ່ງ', 'ສອງ', 'ສາມ', 'ສີ່', 'ຫ້າ', 'ຫົກ', 'ເຈັດ', 'ແປດ', 'ເກົ້າ'];

    $convertGroup = function($n) use ($units) {
        $str = (string)$n;
        $len = strlen($str);
        $res = '';
        for ($i = 0; $i < $len; $i++) {
            $digit = (int)$str[$i];
            $pos = $len - $i - 1;

            if ($digit == 0) continue;

            if ($pos == 1) { // tens
                if ($digit == 1) {
                    $res .= 'ສິບ';
                } else if ($digit == 2) {
                    $res .= 'ຊາວ';
                } else {
                    $res .= $units[$digit] . 'ສິບ';
                }
            } else if ($pos == 0) { // ones
                if ($digit == 1 && $len > 1 && (int)$str[$len - 2] > 0) {
                    $res .= 'ເອັດ';
                } else {
                    $res .= $units[$digit];
                }
            } else {
                $posNames = [2 => 'ຮ້ອຍ', 3 => 'ພັນ', 4 => 'ໝື່ນ', 5 => 'ແສນ'];
                $res .= $units[$digit] . ($posNames[$pos] ?? '');
            }
        }
        return $res;
    };

    $intPart = (int)floor($number);
    if ($intPart <= 0) return 'ສູນກີບຖ້ວນ';

    $result = '';
    if ($intPart >= 1000000) {
        $millions = (int)floor($intPart / 1000000);
        $remainder = $intPart % 1000000;
        $result .= $convertGroup($millions) . 'ລ້ານ';
        if ($remainder > 0) {
            $result .= $convertGroup($remainder);
        }
    } else {
        $result = $convertGroup($intPart);
    }

    return $result . 'ກີບຖ້ວນ';
}

/**
 * Get current page name from URL
 */
function getCurrentPage() {
    $page = basename($_SERVER['PHP_SELF'], '.php');
    return $page;
}

/**
 * Check if current page matches given page name
 */
function isActivePage($pageName) {
    return getCurrentPage() === $pageName ? 'active' : '';
}

/**
 * Get shop settings (cached per-request)
 */
function getShopSettings() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $db = getDB();
    $stmt = $db->query("SELECT * FROM shop_settings LIMIT 1");
    $cached = $stmt->fetch();
    if ($cached) {
        $imagesDir = __DIR__ . '/../assets/images/';
        foreach (['logo', 'favicon', 'qr_code', 'footer_image'] as $imgKey) {
            if (!empty($cached[$imgKey]) && !file_exists($imagesDir . $cached[$imgKey])) {
                $cached[$imgKey] = '';
            }
        }
        $mediaDir = __DIR__ . '/../assets/media/';
        if (!empty($cached['cfd_media']) && !file_exists($mediaDir . $cached['cfd_media'])) {
            $cached['cfd_media'] = '';
        }
    }
    return $cached;
}

/**
 * Get unread notifications count
 */
function getUnreadNotificationsCount() {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
    $row = $stmt->fetch();
    return $row['count'];
}

/**
 * Get dashboard stats (consolidated: 5 queries → 1 query)
 */
function getDashboardStats($startDate, $endDate) {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_sales,
            COALESCE(SUM(CASE 
                WHEN payment_method = 'cash' THEN total_amount 
                WHEN payment_method = 'split' THEN cash_amount 
                ELSE 0 
            END), 0) as cash_sales,
            COALESCE(SUM(CASE 
                WHEN payment_method = 'transfer' THEN total_amount 
                WHEN payment_method = 'split' THEN transfer_amount 
                ELSE 0 
            END), 0) as transfer_sales,
            COUNT(*) as bill_count
        FROM sales 
        WHERE payment_status != 'cancelled' 
        AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $stats = $stmt->fetch();

    // Items sold (requires join, separate query)
    $stmt2 = $db->prepare("
        SELECT COALESCE(SUM(si.quantity), 0) as count 
        FROM sale_items si 
        JOIN sales s ON si.sale_id = s.id 
        WHERE s.payment_status != 'cancelled' 
        AND DATE(s.created_at) BETWEEN ? AND ?
    ");
    $stmt2->execute([$startDate, $endDate]);
    $stats['items_sold'] = $stmt2->fetch()['count'];

    // Notifications
    $stats['notifications'] = getUnreadNotificationsCount();

    return $stats;
}

/**
 * Get income/expense report stats (consolidated: 6 queries → 2 queries)
 */
function getIncomeExpenseStats($startDate, $endDate) {
    $db = getDB();

    // All sales stats in one query
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_income,
            COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) as cash_sales,
            COALESCE(SUM(CASE WHEN payment_method = 'transfer' THEN total_amount ELSE 0 END), 0) as transfer_sales
        FROM sales 
        WHERE payment_status != 'cancelled' 
        AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $stats = $stmt->fetch();

    // All expense stats in one query
    $stmt2 = $db->prepare("
        SELECT 
            COALESCE(SUM(e.amount), 0) as total_expense,
            COALESCE(SUM(CASE WHEN ec.name != 'ຊື້ຢາເຂົ້າ' OR ec.name IS NULL THEN e.amount ELSE 0 END), 0) as general_expense,
            COALESCE(SUM(CASE WHEN ec.name = 'ຊື້ຢາເຂົ້າ' THEN e.amount ELSE 0 END), 0) as import_expense
        FROM expenses e 
        LEFT JOIN expense_categories ec ON e.category_id = ec.id 
        WHERE e.expense_date BETWEEN ? AND ?
    ");
    $stmt2->execute([$startDate, $endDate]);
    $expStats = $stmt2->fetch();

    $stats['total_expense'] = $expStats['total_expense'];
    $stats['general_expense'] = $expStats['general_expense'];
    $stats['import_expense'] = $expStats['import_expense'];
    $stats['profit'] = $stats['total_income'] - $stats['total_expense'];

    return $stats;
}

/**
 * Get sales chart data
 */
function getSalesChartData($startDate, $endDate) {
    $db = getDB();
    $labels = [];
    $data = [];

    if ($startDate === $endDate) {
        // Hourly for a single day
        $hours = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];
        $labels = $hours;
        
        $stmt = $db->prepare("SELECT HOUR(created_at) as hr, SUM(total_amount) as total FROM sales WHERE payment_status != 'cancelled' AND DATE(created_at) = ? GROUP BY HOUR(created_at)");
        $stmt->execute([$startDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($hours as $h) {
            $hrInt = (int)substr($h, 0, 2);
            $data[] = isset($rows[$hrInt]) ? (float)$rows[$hrInt] : 0;
        }
    } else {
        // Daily for date range
        $period = new DatePeriod(
            new DateTime($startDate),
            new DateInterval('P1D'),
            (new DateTime($endDate))->modify('+1 day')
        );
        
        $stmt = $db->prepare("SELECT DATE(created_at) as dt, SUM(total_amount) as total FROM sales WHERE payment_status != 'cancelled' AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at)");
        $stmt->execute([$startDate, $endDate]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($period as $date) {
            $dtStr = $date->format('Y-m-d');
            $labels[] = $date->format('d/m');
            $data[] = isset($rows[$dtStr]) ? (float)$rows[$dtStr] : 0;
        }
    }

    return ['labels' => $labels, 'data' => $data];
}

/**
 * Get income vs expense chart data
 */
function getIncomeExpenseChartData($startDate, $endDate) {
    $db = getDB();
    $labels = [];
    $income = [];
    $expense = [];
    $profit = [];

    if ($startDate === $endDate) {
        // Hourly for a single day
        $hours = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'];
        $labels = $hours;

        // Sales (Income)
        $stmt = $db->prepare("SELECT HOUR(created_at) as hr, SUM(total_amount) as total FROM sales WHERE payment_status != 'cancelled' AND DATE(created_at) = ? GROUP BY HOUR(created_at)");
        $stmt->execute([$startDate]);
        $salesRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Expenses (all grouped at 09:00 for hourly presentation simplicity, or 0)
        $stmt = $db->prepare("SELECT SUM(amount) as total FROM expenses WHERE expense_date = ?");
        $stmt->execute([$startDate]);
        $totalExp = (float)$stmt->fetch()['total'];

        foreach ($hours as $h) {
            $hrInt = (int)substr($h, 0, 2);
            $incVal = isset($salesRows[$hrInt]) ? (float)$salesRows[$hrInt] : 0;
            $expVal = ($hrInt === 9) ? $totalExp : 0;

            $income[] = $incVal;
            $expense[] = $expVal;
            $profit[] = $incVal - $expVal;
        }
    } else {
        // Daily for date range
        $period = new DatePeriod(
            new DateTime($startDate),
            new DateInterval('P1D'),
            (new DateTime($endDate))->modify('+1 day')
        );

        // Sales (Income)
        $stmt = $db->prepare("SELECT DATE(created_at) as dt, SUM(total_amount) as total FROM sales WHERE payment_status != 'cancelled' AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at)");
        $stmt->execute([$startDate, $endDate]);
        $salesRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Expenses
        $stmt = $db->prepare("SELECT expense_date as dt, SUM(amount) as total FROM expenses WHERE expense_date BETWEEN ? AND ? GROUP BY expense_date");
        $stmt->execute([$startDate, $endDate]);
        $expenseRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($period as $date) {
            $dtStr = $date->format('Y-m-d');
            $labels[] = $date->format('d/m');
            
            $incVal = isset($salesRows[$dtStr]) ? (float)$salesRows[$dtStr] : 0;
            $expVal = isset($expenseRows[$dtStr]) ? (float)$expenseRows[$dtStr] : 0;

            $income[] = $incVal;
            $expense[] = $expVal;
            $profit[] = $incVal - $expVal;
        }
    }

    return [
        'labels' => $labels,
        'income' => $income,
        'expense' => $expense,
        'profit' => $profit
    ];
}

/**
 * Get top-selling products for a date range
 */
function getTopSellingProducts($startDate, $endDate, $limit = 5) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT si.product_name, SUM(si.quantity) as total_qty, SUM(si.total_price) as total_revenue
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        WHERE s.payment_status != 'cancelled'
        AND DATE(s.created_at) BETWEEN ? AND ?
        GROUP BY si.product_id, si.product_name
        ORDER BY total_qty DESC
        LIMIT ?
    ");
    $stmt->execute([$startDate, $endDate, $limit]);
    return $stmt->fetchAll();
}

/**
 * Get products expiring soon (within N days)
 */
function getExpiringProducts($days = 30) {
    $db = getDB();
    $today = date('Y-m-d');
    $targetDate = date('Y-m-d', strtotime("+{$days} days"));
    $stmt = $db->prepare("
        SELECT pb.id as batch_id, pb.batch_number, pb.quantity as stock_quantity, pb.expiry_date, p.name, p.unit, c.name as category_name, p.id as product_id
        FROM product_batches pb
        JOIN products p ON pb.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 
        AND pb.quantity > 0
        AND pb.expiry_date >= ?
        AND pb.expiry_date <= ?
        
        UNION ALL
        
        SELECT NULL as batch_id, NULL as batch_number, p.stock_quantity, p.expiry_date, p.name, p.unit, c.name as category_name, p.id as product_id
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 
        AND p.expiry_date IS NOT NULL
        AND p.expiry_date >= ?
        AND p.expiry_date <= ?
        AND (SELECT COUNT(*) FROM product_batches pb WHERE pb.product_id = p.id AND pb.quantity > 0) = 0
        
        ORDER BY expiry_date ASC
    ");
    $stmt->execute([$today, $targetDate, $today, $targetDate]);
    return $stmt->fetchAll();
}

/**
 * Get expired products
 */
function getExpiredProducts() {
    $db = getDB();
    $today = date('Y-m-d');
    $stmt = $db->prepare("
        SELECT pb.id as batch_id, pb.batch_number, pb.quantity as stock_quantity, pb.expiry_date, p.name, p.unit, c.name as category_name, p.id as product_id
        FROM product_batches pb
        JOIN products p ON pb.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 
        AND pb.quantity > 0
        AND pb.expiry_date < ?
        
        UNION ALL
        
        SELECT NULL as batch_id, NULL as batch_number, p.stock_quantity, p.expiry_date, p.name, p.unit, c.name as category_name, p.id as product_id
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 
        AND p.expiry_date IS NOT NULL
        AND p.expiry_date < ?
        AND (SELECT COUNT(*) FROM product_batches pb WHERE pb.product_id = p.id AND pb.quantity > 0) = 0
        
        ORDER BY expiry_date ASC
    ");
    $stmt->execute([$today, $today]);
    return $stmt->fetchAll();
}

/**
 * Deduct stock from the oldest active product batches first (FIFO)
 */
function deductStockFIFO($productId, $quantity) {
    $db = getDB();
    
    // Check if the product has any active batches
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM product_batches WHERE product_id = ? AND quantity > 0");
    $stmt->execute([$productId]);
    $hasBatches = $stmt->fetch()['c'] > 0;
    
    if ($hasBatches) {
        // Fetch active batches sorted by expiry date / created_at (FIFO)
        $stmtBatches = $db->prepare("SELECT * FROM product_batches WHERE product_id = ? AND quantity > 0 ORDER BY expiry_date ASC, id ASC");
        $stmtBatches->execute([$productId]);
        $batches = $stmtBatches->fetchAll();
        
        $remainingToDeduct = $quantity;
        
        $stmtUpdateBatch = $db->prepare("UPDATE product_batches SET quantity = ? WHERE id = ?");
        
        foreach ($batches as $batch) {
            if ($remainingToDeduct <= 0) break;
            
            $batchQty = $batch['quantity'];
            if ($batchQty >= $remainingToDeduct) {
                // Deduct fully from this batch
                $newQty = $batchQty - $remainingToDeduct;
                $stmtUpdateBatch->execute([$newQty, $batch['id']]);
                $remainingToDeduct = 0;
            } else {
                // Deduct as much as possible, then move to next batch
                $stmtUpdateBatch->execute([0, $batch['id']]);
                $remainingToDeduct -= $batchQty;
            }
        }
    }
    
    // Always deduct from the main products table to keep stock_quantity consistent (protected against negative values)
    $stmtProduct = $db->prepare("UPDATE products SET stock_quantity = CASE WHEN stock_quantity >= ? THEN stock_quantity - ? ELSE 0 END WHERE id = ?");
    $stmtProduct->execute([$quantity, $quantity, $productId]);
}

/**
 * Restore stock to the latest active product batch when a sale is cancelled
 */
function restoreStockFIFO($productId, $quantity) {
    $db = getDB();
    
    // Check if the product has any active batches
    $stmt = $db->prepare("SELECT id, quantity FROM product_batches WHERE product_id = ? ORDER BY expiry_date DESC, id DESC LIMIT 1");
    $stmt->execute([$productId]);
    $batch = $stmt->fetch();
    
    if ($batch) {
        // Restore to the latest batch
        $stmtUpdateBatch = $db->prepare("UPDATE product_batches SET quantity = quantity + ? WHERE id = ?");
        $stmtUpdateBatch->execute([$quantity, $batch['id']]);
    }
    
    // Always restore in main products table
    $stmtProduct = $db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
    $stmtProduct->execute([$quantity, $productId]);
}

/**
 * Get all batches for a specific product
 */
function getProductBatches($productId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM product_batches WHERE product_id = ? ORDER BY expiry_date ASC, id ASC");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}


/**
 * Sanitize input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Get products with low stock (at or below min_stock threshold)
 */
function getLowStockProducts($limit = 20) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_active = 1 
        AND p.stock_quantity <= p.min_stock
        ORDER BY p.stock_quantity ASC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Get payment method label in Lao
 */
function getPaymentMethodLabel($method) {
    $labels = [
        'cash' => 'ເງິນສົດ',
        'transfer' => 'ໂອນ',
        'credit' => 'ຕິດໜີ້',
        'split' => 'ແບ່ງຈ່າຍ',
    ];
    return $labels[$method] ?? $method;
}

/**
 * Get payment method badge class
 */
function getPaymentMethodBadge($method) {
    $badges = [
        'cash' => 'badge-success',
        'transfer' => 'badge-info',
        'credit' => 'badge-warning',
        'split' => 'badge-primary',
    ];
    return $badges[$method] ?? 'badge-secondary';
}

/**
 * Get payment status label
 */
function getPaymentStatusLabel($status) {
    $labels = [
        'paid' => 'ຊຳລະແລ້ວ',
        'pending' => 'ລໍຖ້າ',
        'cancelled' => 'ຍົກເລີກ',
    ];
    return $labels[$status] ?? $status;
}

/**
 * Get payment status badge class
 */
function getPaymentStatusBadge($status) {
    $badges = [
        'paid' => 'badge-success',
        'pending' => 'badge-warning',
        'cancelled' => 'badge-danger',
    ];
    return $badges[$status] ?? 'badge-info';
}

/**
 * Export data as CSV download
 */
function exportCSV($filename, $headers, $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // UTF-8 BOM for Excel compatibility
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// Load QR Code Helper (100% Offline Generator)
require_once __DIR__ . '/qrcode_helper.php';

