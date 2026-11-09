<?php
/**
 * ປະຫວັດການເຄື່ອນໄຫວ / ບັນທຶກການໃຊ້ງານ - User Activity Logs & Audit Trail
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireLogin();

// Restrict access to admin, manager, and developer
if (!hasRole(['admin', 'manager', 'developer'])) {
    header('Location: ' . BASE_URL . 'pos.php');
    exit;
}

$pageTitle = 'ປະຫວັດການໃຊ້ງານ';

$db = getDB();

// Filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$userIdFilter = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$categoryFilter = $_GET['category'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

// Export CSV handler
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportQuery = "
        SELECT l.*, u.full_name, u.role
        FROM user_activity_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE DATE(l.created_at) BETWEEN ? AND ?
    ";
    $expParams = [$startDate, $endDate];

    if ($userIdFilter > 0) {
        $exportQuery .= " AND l.user_id = ?";
        $expParams[] = $userIdFilter;
    }
    if ($categoryFilter !== 'all') {
        $exportQuery .= " AND l.category = ?";
        $expParams[] = $categoryFilter;
    }
    if ($search !== '') {
        $exportQuery .= " AND (l.description LIKE ? OR l.username LIKE ? OR l.ip_address LIKE ?)";
        $expParams[] = "%{$search}%";
        $expParams[] = "%{$search}%";
        $expParams[] = "%{$search}%";
    }
    $exportQuery .= " ORDER BY l.id DESC LIMIT 2000";

    $stmtExp = $db->prepare($exportQuery);
    $stmtExp->execute($expParams);
    $logsExp = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Ymd_His') . '.csv"');
    
    // Output UTF-8 BOM for Excel support
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'ວັນທີ-ເວລາ', 'ຊື່ຜູ້ໃຊ້', 'ຊື່ແທ້', 'ບົດບາດ', 'ໝວດໝູ່', 'ການກະທຳ', 'ລາຍລະອຽດ', 'IP Address']);
    
    foreach ($logsExp as $row) {
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['username'] ?? '-',
            $row['full_name'] ?? '-',
            $row['role'] ?? '-',
            $row['category'],
            $row['action'],
            $row['description'],
            $row['ip_address'] ?? '-'
        ]);
    }
    fclose($output);
    exit;
}

// Build query
$whereClauses = ["DATE(l.created_at) BETWEEN ? AND ?"];
$params = [$startDate, $endDate];

if ($userIdFilter > 0) {
    $whereClauses[] = "l.user_id = ?";
    $params[] = $userIdFilter;
}

if ($categoryFilter !== 'all') {
    $whereClauses[] = "l.category = ?";
    $params[] = $categoryFilter;
}

if ($search !== '') {
    $whereClauses[] = "(l.description LIKE ? OR l.username LIKE ? OR l.ip_address LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$whereSql = implode(' AND ', $whereClauses);

// Count total matching records for pagination
$countStmt = $db->prepare("SELECT COUNT(*) FROM user_activity_logs l WHERE {$whereSql}");
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

// Fetch records for current page
$query = "
    SELECT l.*, u.full_name, u.role as user_role
    FROM user_activity_logs l
    LEFT JOIN users u ON l.user_id = u.id
    WHERE {$whereSql}
    ORDER BY l.id DESC
    LIMIT {$limit} OFFSET {$offset}
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get KPI Stats for selected date range
$statsQuery = $db->prepare("
    SELECT 
        COUNT(*) as total_all,
        SUM(CASE WHEN category = 'auth' THEN 1 ELSE 0 END) as total_auth,
        SUM(CASE WHEN category = 'sales' THEN 1 ELSE 0 END) as total_sales,
        SUM(CASE WHEN category = 'inventory' THEN 1 ELSE 0 END) as total_inventory,
        SUM(CASE WHEN category = 'shift' THEN 1 ELSE 0 END) as total_shift
    FROM user_activity_logs
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$statsQuery->execute([$startDate, $endDate]);
$stats = $statsQuery->fetch(PDO::FETCH_ASSOC) ?: [
    'total_all' => 0, 'total_auth' => 0, 'total_sales' => 0, 'total_inventory' => 0, 'total_shift' => 0
];

// Get users list for filter dropdown
$users = $db->query("SELECT id, username, full_name, role FROM users ORDER BY full_name ASC, username ASC")->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">ປະຫວັດການໃຊ້ງານ (User Activity Logs)</h1>
            <p style="font-size:0.85rem; color:var(--text-medium); margin-top:2px;">
                ຕິດຕາມ ແລະ ກວດສອບທຸກການເຄື່ອນໄຫວໃນລະບົບ (Audit Trail) ເຊັ່ນ: ການເຂົ້າສູ່ລະບົບ, ການຂາຍ, ການຍົກເລີກບິນ, ການປັບສະຕ໋ອກ ແລະ ການຕັ້ງຄ່າ
            </p>
        </div>
        <div style="display: flex; gap: 8px;">
            <?php
            $exportParams = $_GET;
            $exportParams['export'] = 'csv';
            $exportUrl = 'activity_logs.php?' . http_build_query($exportParams);
            ?>
            <a href="<?= $exportUrl ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-file-excel"></i> ສົ່ງອອກ CSV
            </a>
        </div>
    </div>

    <div class="content-area">
        <!-- Stats KPI Cards -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(46, 125, 50, 0.12); color: var(--primary);">
                    <i class="fas fa-list-ul"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">ການເຄື່ອນໄຫວທັງໝົດ</span>
                    <strong class="stat-value"><?= number_format((int)$stats['total_all']) ?> ຄັ້ງ</strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(2, 119, 189, 0.12); color: #0277bd;">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">ເຂົ້າ/ອອກລະບົບ (Auth)</span>
                    <strong class="stat-value" style="color: #0277bd;"><?= number_format((int)$stats['total_auth']) ?> ຄັ້ງ</strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(0, 135, 90, 0.12); color: #00875a;">
                    <i class="fas fa-cash-register"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">ການຂາຍ & ບິນ (Sales)</span>
                    <strong class="stat-value" style="color: #00875a;"><?= number_format((int)$stats['total_sales']) ?> ຄັ້ງ</strong>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(178, 106, 0, 0.12); color: #b26a00;">
                    <i class="fas fa-boxes"></i>
                </div>
                <div class="stat-info">
                    <span class="stat-label">ສາງຢາ & ສິນຄ້າ (Stock)</span>
                    <strong class="stat-value" style="color: #b26a00;"><?= number_format((int)$stats['total_inventory']) ?> ຄັ້ງ</strong>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card" style="margin-bottom: 20px;">
            <form method="GET" action="activity_logs.php" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)) 120px 80px; gap: 12px; align-items: flex-end;">
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 600; margin-bottom: 4px; display: block;">ວັນທີເລີ່ມຕົ້ນ</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 600; margin-bottom: 4px; display: block;">ວັນທີສິ້ນສຸດ</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 600; margin-bottom: 4px; display: block;">ພະນັກງານ</label>
                    <select name="user_id" class="form-control">
                        <option value="0">-- ທຸກພະນັກງານ --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $userIdFilter === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['full_name'] ?: $u['username']) ?> (<?= htmlspecialchars($u['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 600; margin-bottom: 4px; display: block;">ໝວດໝູ່</label>
                    <select name="category" class="form-control">
                        <option value="all" <?= $categoryFilter === 'all' ? 'selected' : '' ?>>-- ທຸກໝວດໝູ່ --</option>
                        <option value="auth" <?= $categoryFilter === 'auth' ? 'selected' : '' ?>>ເຂົ້າ/ອອກລະບົບ (Auth)</option>
                        <option value="sales" <?= $categoryFilter === 'sales' ? 'selected' : '' ?>>ການຂາຍ & ບິນ (Sales)</option>
                        <option value="shift" <?= $categoryFilter === 'shift' ? 'selected' : '' ?>>ກະຂາຍ (Shift)</option>
                        <option value="inventory" <?= $categoryFilter === 'inventory' ? 'selected' : '' ?>>ສາງຢາ & ສິນຄ້າ (Stock)</option>
                        <option value="expense" <?= $categoryFilter === 'expense' ? 'selected' : '' ?>>ລາຍຈ່າຍ (Expense)</option>
                        <option value="settings" <?= $categoryFilter === 'settings' ? 'selected' : '' ?>>ຕັ້ງຄ່າ & ພະນັກງານ (Settings)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="font-size: 0.8rem; font-weight: 600; margin-bottom: 4px; display: block;">ຄົ້ນຫາ</label>
                    <input type="text" name="search" class="form-control" placeholder="ຄຳອະທິບາຍ, IP..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-filter"></i> ກັ່ນຕອງ
                    </button>
                </div>
                <div>
                    <a href="activity_logs.php" class="btn btn-outline" style="width: 100%; text-align: center;" title="ລ້າງຕົວກອງ">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Logs Table Card -->
        <?php if (empty($logs)): ?>
        <div class="empty-state" style="padding: 60px 20px; text-align: center; background: #fff; border-radius: 16px;">
            <i class="fas fa-history" style="font-size: 3rem; color: var(--text-light); margin-bottom: 12px; display: block;"></i>
            <h4 style="margin-bottom: 8px; color: var(--text-dark);">ບໍ່ພົບປະຫວັດການເຄື່ອນໄຫວ</h4>
            <p style="color: var(--text-medium); margin-bottom: 15px;">ບໍ່ມີລາຍການບັນທຶກການໃຊ້ງານທີ່ກົງກັບເງື່ອນໄຂການກັ່ນຕອງໃນຊ່ວງເວລານີ້</p>
            <a href="activity_logs.php" class="btn btn-outline btn-sm">
                <i class="fas fa-calendar-day"></i> ລ້າງຕົວກອງ (7 ວັນລ່າສຸດ)
            </a>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table" id="activityLogsTable" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 160px;">ວັນທີ & ເວລາ</th>
                            <th style="width: 180px;">ຜູ້ໃຊ້ງານ</th>
                            <th style="width: 130px;">ໝວດໝູ່</th>
                            <th>ລາຍລະອຽດການເຄື່ອນໄຫວ</th>
                            <th style="width: 140px;">IP Address</th>
                            <th style="width: 80px; text-align: center;">ຂໍ້ມູນ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): 
                            // Category Badge Styles
                            $catBadge = [
                                'auth' => ['bg' => '#e1f5fe', 'color' => '#0277bd', 'icon' => 'fa-key', 'label' => 'Auth'],
                                'sales' => ['bg' => '#e8f5e9', 'color' => '#2e7d32', 'icon' => 'fa-shopping-cart', 'label' => 'Sales'],
                                'shift' => ['bg' => '#f3e5f5', 'color' => '#7b1fa2', 'icon' => 'fa-clock', 'label' => 'Shift'],
                                'inventory' => ['bg' => '#fff8e1', 'color' => '#b26a00', 'icon' => 'fa-boxes', 'label' => 'Stock'],
                                'expense' => ['bg' => '#ffebee', 'color' => '#c62828', 'icon' => 'fa-wallet', 'label' => 'Expense'],
                                'settings' => ['bg' => '#ede7f6', 'color' => '#512da8', 'icon' => 'fa-cog', 'label' => 'Settings'],
                            ][$log['category']] ?? ['bg' => '#f5f5f5', 'color' => '#616161', 'icon' => 'fa-info-circle', 'label' => $log['category']];

                            $hasDetails = !empty($log['details']) && $log['details'] !== 'null';
                        ?>
                        <tr>
                            <td style="white-space: nowrap; font-size: 0.82rem; color: var(--text-medium);">
                                <i class="far fa-clock" style="margin-right: 4px; opacity: 0.6;"></i>
                                <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary-bg); color: var(--primary); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">
                                        <?= strtoupper(mb_substr($log['username'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 0.88rem; color: var(--text-dark);">
                                            <?= htmlspecialchars($log['full_name'] ?: ($log['username'] ?? 'ລະບົບ')) ?>
                                        </div>
                                        <small style="color: var(--text-light); font-size: 0.75rem;">
                                            @<?= htmlspecialchars($log['username'] ?? 'system') ?>
                                            <?php if (!empty($log['user_role'])): ?>
                                                &bull; <span style="text-transform: capitalize;"><?= htmlspecialchars($log['user_role']) ?></span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= $catBadge['bg'] ?>; color: <?= $catBadge['color'] ?>; font-weight: 600; gap: 4px;">
                                    <i class="fas <?= $catBadge['icon'] ?>" style="font-size: 10px;"></i>
                                    <?= $catBadge['label'] ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size: 0.88rem; color: var(--text-dark); line-height: 1.4;">
                                    <?= htmlspecialchars($log['description']) ?>
                                </div>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--text-light); font-family: monospace;">
                                <i class="fas fa-network-wired" style="font-size: 10px; margin-right: 3px; opacity: 0.6;"></i>
                                <?= htmlspecialchars($log['ip_address'] ?? '-') ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($hasDetails): ?>
                                    <button type="button" class="btn btn-outline btn-sm" style="padding: 4px 8px; font-size: 11px;" onclick="viewLogDetails(<?= htmlspecialchars(json_encode($log, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)" title="ເບິ່ງລາຍລະອຽດ">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                <?php else: ?>
                                    <span style="color: var(--text-light); font-size: 11px;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($totalPages > 1): ?>
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-top: 1px solid var(--border-color); flex-wrap: wrap; gap: 10px;">
                <div style="font-size: 0.85rem; color: var(--text-medium);">
                    ສະແດງ <?= $offset + 1 ?> ຫາ <?= min($totalRecords, $offset + $limit) ?> ຈາກທັງໝົດ <?= number_format($totalRecords) ?> ລາຍການ
                </div>
                <div style="display: flex; gap: 4px;">
                    <?php if ($page > 1): 
                        $prevParams = $_GET;
                        $prevParams['page'] = $page - 1;
                    ?>
                        <a href="activity_logs.php?<?= http_build_query($prevParams) ?>" class="btn btn-outline btn-sm">
                            <i class="fas fa-chevron-left"></i> ກ່ອນໜ້າ
                        </a>
                    <?php endif; ?>

                    <span class="btn btn-sm" style="background: var(--primary-bg); color: var(--primary); font-weight: 700; cursor: default;">
                        ໜ້າ <?= $page ?> / <?= $totalPages ?>
                    </span>

                    <?php if ($page < $totalPages): 
                        $nextParams = $_GET;
                        $nextParams['page'] = $page + 1;
                    ?>
                        <a href="activity_logs.php?<?= http_build_query($nextParams) ?>" class="btn btn-outline btn-sm">
                            ຖັດໄປ <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Details Modal -->
<div class="modal-overlay" id="logDetailsModal">
    <div class="modal-card" style="max-width: 520px; width: 90%;">
        <div class="modal-header">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--text-dark);">
                <i class="fas fa-info-circle" style="color: var(--primary); margin-right: 6px;"></i> ລາຍລະອຽດການເຄື່ອນໄຫວ
            </h3>
            <button class="modal-close" onclick="closeModal('logDetailsModal')">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <div style="margin-bottom: 14px;">
                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-light); font-weight: 700; display: block; margin-bottom: 2px;">ການກະທຳ</label>
                <div id="modalLogAction" style="font-weight: 600; font-size: 0.95rem; color: var(--text-dark);"></div>
            </div>
            <div style="margin-bottom: 14px;">
                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-light); font-weight: 700; display: block; margin-bottom: 2px;">ລາຍລະອຽດ</label>
                <div id="modalLogDesc" style="font-size: 0.9rem; color: var(--text-medium); line-height: 1.4;"></div>
            </div>
            <div style="margin-bottom: 14px;">
                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-light); font-weight: 700; display: block; margin-bottom: 2px;">ອຸປະກອນ & Browser</label>
                <div id="modalLogAgent" style="font-size: 0.78rem; color: var(--text-light); word-break: break-all; background: #f8fafc; padding: 8px 10px; border-radius: 6px; border: 1px solid #e2e8f0;"></div>
            </div>
            <div>
                <label style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-light); font-weight: 700; display: block; margin-bottom: 2px;">ຂໍ້ມູນເພີ່ມເຕີມ (JSON Data)</label>
                <pre id="modalLogJson" style="background: #1e293b; color: #38bdf8; padding: 12px; border-radius: 8px; font-size: 0.8rem; overflow-x: auto; max-height: 180px;"></pre>
            </div>
        </div>
        <div class="modal-footer" style="padding: 12px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-outline btn-sm" onclick="closeModal('logDetailsModal')">ປິດ</button>
        </div>
    </div>
</div>

<script>
function viewLogDetails(log) {
    document.getElementById('modalLogAction').textContent = `${log.action} (${log.category})`;
    document.getElementById('modalLogDesc').textContent = log.description;
    document.getElementById('modalLogAgent').textContent = log.user_agent || 'ບໍ່ມີຂໍ້ມູນ';
    
    let formattedJson = 'ບໍ່ມີຂໍ້ມູນເພີ່ມເຕີມ';
    if (log.details) {
        try {
            const parsed = typeof log.details === 'string' ? JSON.parse(log.details) : log.details;
            formattedJson = JSON.stringify(parsed, null, 2);
        } catch (e) {
            formattedJson = log.details;
        }
    }
    document.getElementById('modalLogJson').textContent = formattedJson;
    openModal('logDetailsModal');
}
</script>

<?php include 'includes/footer.php'; ?>
