<?php
/**
 * ບັນທຶກລາຍຈ່າຍ - Expense Records
 * ລະບົບຂາຍຢາດີ - POINT OF SALE
 */

require_once 'includes/functions.php';
requireRoles(['admin', 'manager']);

$pageTitle = 'ບັນທຶກລາຍຈ່າຍ';

$startDate = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$endDate = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT e.title, ec.name as category_name, e.amount, e.description, e.expense_date FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id WHERE e.expense_date BETWEEN ? AND ? ORDER BY e.expense_date DESC");
        $stmt->execute([$startDate, $endDate]);
        $rows = $stmt->fetchAll();
        
        $csvHeaders = ['ລາຍການ', 'ໝວດໝູ່', 'ຈໍານວນ', 'ລາຍລະອຽດ', 'ວັນທີ'];
        $csvRows = array_map(function($r) {
            return [$r['title'], $r['category_name'] ?: '-', $r['amount'], $r['description'] ?: '-', $r['expense_date']];
        }, $rows);
        
        exportCSV('expenses_' . $startDate . '_' . $endDate . '.csv', $csvHeaders, $csvRows);
    } catch (Exception $e) {
        // Fall through
    }
}

// Monthly total
try {
    $db = getDB();
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $stmt->execute([date('Y-m-01'), date('Y-m-t')]);
    $monthlyTotal = $stmt->fetch()['total'];
} catch (Exception $e) {
    $monthlyTotal = 0;
}

// Get expense categories
try {
    $expenseCategories = $db->query("SELECT * FROM expense_categories ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $expenseCategories = [];
}

// Get expenses
try {
    $sql = "SELECT e.*, ec.name as category_name FROM expenses e LEFT JOIN expense_categories ec ON e.category_id = ec.id WHERE e.expense_date BETWEEN ? AND ?";
    $params = [$startDate, $endDate];
    
    if (!empty($search)) {
        $sql .= " AND (e.title LIKE ? OR ec.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY e.expense_date DESC, e.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $expenses = $stmt->fetchAll();
} catch (Exception $e) {
    $expenses = [];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">ບັນທຶກລາຍຈ່າຍ</h1>
        <div class="date-filters">
            <button class="btn-filter <?= ($startDate === date('Y-m-d') && $endDate === date('Y-m-d')) ? 'active' : '' ?>" data-filter="today" onclick="setDateFilter('today')">ມື້ນີ້</button>
            <button class="btn-filter <?= ($startDate === date('Y-m-01')) ? 'active' : '' ?>" data-filter="month" onclick="setDateFilter('month')">ເດືອນນີ້</button>
            <input type="date" class="date-input" id="startDate" value="<?= $startDate ?>" onchange="onDateChange()">
            <span class="date-separator">-</span>
            <input type="date" class="date-input" id="endDate" value="<?= $endDate ?>" onchange="onDateChange()">
        </div>
    </div>

    <div class="content-area">
        <!-- Monthly Summary Header -->
        <div class="expense-header animate-in">
            <h4>ລວມລາຍຈ່າຍເດືອນນີ້</h4>
            <div class="amount"><?= formatCurrency($monthlyTotal) ?> ກີບ</div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <button class="btn btn-primary" onclick="openAddExpenseModal()">
                <i class="fas fa-plus"></i> ເພີ່ມລາຍການໃໝ່
            </button>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="expenseSearch" placeholder="ຄົ້ນຫາ..." value="<?= $search ?>" onkeyup="if(event.key==='Enter')searchExpenses()">
            </div>
            <a href="?export=csv&start=<?= $startDate ?>&end=<?= $endDate ?>" class="btn btn-outline btn-sm" title="ສົ່ງອອກ CSV">
                <i class="fas fa-file-csv"></i> ສົ່ງອອກ
            </a>
        </div>

        <!-- Expense Table -->
        <?php if (empty($expenses)): ?>
        <div class="empty-state" style="padding:60px 20px; text-align:center;">
            <i class="fas fa-wallet" style="font-size:3rem; color:var(--text-light); margin-bottom:12px;"></i>
            <h4 style="margin-bottom:8px; color:var(--text-dark);">ບໍ່ມີລາຍການລາຍຈ່າຍ</h4>
            <p style="color:var(--text-light); margin-bottom:20px;">ຍັງບໍ່ມີຂໍ້ມູນລາຍຈ່າຍໃນຊ່ວງວັນທີທີ່ເລືອກ</p>
            <button class="btn btn-primary btn-sm" onclick="openAddExpenseModal()">
                <i class="fas fa-plus"></i> ເພີ່ມລາຍຈ່າຍໃໝ່
            </button>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="table-responsive">
                <table class="table" id="expenseTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>ລາຍການ</th>
                            <th>ໝວດໝູ່</th>
                            <th>ຈຳນວນເງິນ</th>
                            <th>ວັນທີ</th>
                            <th>ໝາຍເຫດ</th>
                            <th>ຈັດການ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $i => $exp): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><strong><?= sanitize($exp['title']) ?></strong></td>
                            <td><span class="badge badge-info"><?= sanitize($exp['category_name'] ?? 'ອື່ນໆ') ?></span></td>
                            <td class="text-danger fw-bold"><?= formatCurrency($exp['amount']) ?> ກີບ</td>
                            <td><?= formatDate($exp['expense_date']) ?></td>
                            <td><?= sanitize($exp['description'] ?? '-') ?></td>
                            <td>
                                <div style="display:flex;gap:4px;">
                                    <button class="btn-icon btn-white btn-sm" onclick="editExpense(<?= $exp['id'] ?>)" title="ແກ້ໄຂ">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon btn-white btn-sm" style="color:var(--danger);" onclick="deleteExpense(<?= $exp['id'] ?>)" title="ລຶບ">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Add Expense Modal -->
<div class="modal-overlay" id="addExpenseModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="expenseModalTitle">ເພີ່ມລາຍຈ່າຍໃໝ່</h3>
            <button class="modal-close" onclick="closeModal('addExpenseModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="expenseForm" onsubmit="saveExpense(event)">
                <input type="hidden" id="expenseId" value="">
                <div class="form-group">
                    <label class="form-label">ຊື່ລາຍການ *</label>
                    <input type="text" class="form-control" id="expenseTitle" required placeholder="ລະບຸຊື່ລາຍການ" oninput="this.style.borderColor=''">
                </div>
                <div class="form-group">
                    <label class="form-label">ໝວດໝູ່</label>
                    <select class="form-control" id="expenseCategory">
                        <option value="">ເລືອກໝວດໝູ່</option>
                        <?php foreach ($expenseCategories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">ຈຳນວນເງິນ (ກີບ) *</label>
                    <input type="number" class="form-control" id="expenseAmount" required min="0" placeholder="0" oninput="this.style.borderColor=''">
                </div>
                <div class="form-group">
                    <label class="form-label">ວັນທີ *</label>
                    <input type="date" class="form-control" id="expenseDate" required value="<?= date('Y-m-d') ?>" onchange="this.style.borderColor=''">
                </div>
                <div class="form-group">
                    <label class="form-label">ໝາຍເຫດ</label>
                    <textarea class="form-control" id="expenseDescription" rows="3" placeholder="ລາຍລະອຽດເພີ່ມເຕີມ..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-white" onclick="closeModal('addExpenseModal')">ຍົກເລີກ</button>
            <button class="btn btn-primary" id="saveExpenseBtn" onclick="saveExpense()">
                <i class="fas fa-save"></i> ບັນທຶກ
            </button>
        </div>
    </div>
</div>

<script>
function loadPageData(startDate, endDate) {
    window.location.href = `expenses.php?start=${startDate}&end=${endDate}`;
}

function openAddExpenseModal() {
    document.getElementById('expenseId').value = '';
    document.getElementById('expenseForm').reset();
    ['expenseTitle', 'expenseAmount', 'expenseDate'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.borderColor = '';
    });
    document.getElementById('expenseModalTitle').textContent = 'ເພີ່ມລາຍຈ່າຍໃໝ່';
    openModal('addExpenseModal');
}

async function saveExpense(e) {
    if (e) e.preventDefault();
    const id = document.getElementById('expenseId').value;
    const title = document.getElementById('expenseTitle').value.trim();
    const amount = document.getElementById('expenseAmount').value;
    const date = document.getElementById('expenseDate').value;
    
    const fields = [
        { id: 'expenseTitle', valid: !!title },
        { id: 'expenseAmount', valid: !!amount && parseFloat(amount) > 0 },
        { id: 'expenseDate', valid: !!date }
    ];
    let firstInvalid = null;
    fields.forEach(f => {
        const el = document.getElementById(f.id);
        if (el) {
            if (!f.valid) {
                el.style.borderColor = 'var(--danger)';
                if (!firstInvalid) firstInvalid = el;
            } else {
                el.style.borderColor = '';
            }
        }
    });

    if (firstInvalid) {
        firstInvalid.focus();
        showToast('ກະລຸນາປ້ອນຂໍ້ມູນທີ່ຈຳເປັນ (ຊື່ລາຍການ, ຈຳນວນເງິນ, ວັນທີ) ໃຫ້ຄົບ', 'warning');
        return;
    }
    
    const data = {
        title: title,
        category_id: document.getElementById('expenseCategory').value || null,
        amount: parseFloat(amount),
        expense_date: date,
        description: document.getElementById('expenseDescription').value,
    };
    
    if (id) {
        data.id = parseInt(id);
    }
    
    const btn = document.getElementById('saveExpenseBtn');
    const origHtml = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ກຳລັງບັນທຶກ...'; }

    try {
        const result = await fetchAPI(`${BASE_URL}api/expenses.php`, {
            method: 'POST',
            body: JSON.stringify(data),
        });
        
        if (result.success) {
            showToast(id ? 'ແກ້ໄຂລາຍຈ່າຍສຳເລັດ' : 'ບັນທຶກລາຍຈ່າຍສຳເລັດ', 'success');
            closeModal('addExpenseModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
        if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
    }
}

async function deleteExpense(id) {
    if (!await confirmDelete('ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການລຶບລາຍຈ່າຍນີ້?')) return;
    
    try {
        const result = await fetchAPI(`${BASE_URL}api/expenses.php?id=${id}`, {
            method: 'DELETE',
        });
        if (result.success) {
            showToast('ລຶບລາຍຈ່າຍສຳເລັດ', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(result.message || 'ເກີດຂໍ້ຜິດພາດ', 'error');
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດ', 'error');
    }
}

async function editExpense(id) {
    try {
        const result = await fetchAPI(`${BASE_URL}api/expenses.php?id=${id}`);
        if (result.success && result.expense) {
            const exp = result.expense;
            document.getElementById('expenseId').value = exp.id;
            document.getElementById('expenseTitle').value = exp.title;
            document.getElementById('expenseCategory').value = exp.category_id || '';
            document.getElementById('expenseAmount').value = Math.round(exp.amount);
            document.getElementById('expenseDate').value = exp.expense_date;
            document.getElementById('expenseDescription').value = exp.description || '';
            
            document.getElementById('expenseModalTitle').textContent = 'ແກ້ໄຂລາຍຈ່າຍ';
            openModal('addExpenseModal');
        } else {
            showToast('ບໍ່ສາມາດດຶງຂໍ້ມູນລາຍຈ່າຍໄດ້', 'error');
        }
    } catch (error) {
        showToast('ເກີດຂໍ້ຜິດພາດໃນການດຶງຂໍ້ມູນ', 'error');
    }
}

function searchExpenses() {
    const search = document.getElementById('expenseSearch').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    window.location.href = `expenses.php?start=${startDate}&end=${endDate}&search=${encodeURIComponent(search)}`;
}
</script>

<?php include 'includes/footer.php'; ?>
