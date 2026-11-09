import shutil
import os

files_to_sync = [
    'includes/functions.php',
    'includes/sidebar.php',
    'staff.php',
    'login.php',
    'pos.php',
    'sales_history.php',
    'tax_report.php',
    'tax_invoice.php',
    'assets/js/pos.js',
    'api/tax_report.php',
    'api/users.php',
    'api/products.php',
    'api/sales.php',
    'api/profit_loss.php',
    'api/expenses.php',
    'profit_loss_report.php',
    'income_expense_report.php',
    'expenses.php',
    'products.php',
    'inventory.php',
    'purchase_orders.php',
    'suppliers.php',
    'barcode_manager.php',
    'index.php',
    'scripts/migrate_roles_pharmacist.php',
    'scripts/migrate_tax_invoice.php'
]

destinations = [
    'desktop-app/src',
    'dist/pospharma-web'
]

for dest in destinations:
    for f in files_to_sync:
        if os.path.exists(f):
            target = os.path.join(dest, f)
            os.makedirs(os.path.dirname(target), exist_ok=True)
            shutil.copy2(f, target)
            print(f"Synced {f} -> {target}")

print("Sync complete!")
