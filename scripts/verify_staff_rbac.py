import asyncio
import os
import sys
from playwright.async_api import async_playwright

BASE_URL = "http://127.0.0.1:8080"
SCREENSHOT_DIR = r"C:\Users\XAI_PC\.gemini\antigravity\brain\cb85b848-6b0f-4cb6-a3cc-6fad0b4b03d1"

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(viewport={"width": 1400, "height": 900})
        page = await context.new_page()

        print("=== Step 1: Login as Admin ===")
        await page.goto(f"{BASE_URL}/login.php")
        await page.fill('input[name="username"]', 'admin')
        await page.fill('input[name="password"]', 'admin123')
        await page.click('button[type="submit"]')
        await page.wait_for_url(f"**/{'index.php'}")
        print("✓ Logged in as Admin.")

        print("=== Step 2: Open Staff Management ===")
        await page.goto(f"{BASE_URL}/staff.php")
        await page.wait_for_selector("#staffTable, .empty-state")
        print("✓ Staff page loaded.")

        # Click Add Staff
        await page.click('button:has-text("ເພີ່ມພະນັກງານ")')
        await page.wait_for_selector("#addStaffModal", state="visible")
        await asyncio.sleep(0.5)

        # Select role 'pharmacist'
        await page.select_option("#staffRole", "pharmacist")
        # Trigger onchange
        await page.evaluate("() => onRoleChanged()")
        await asyncio.sleep(0.3)

        # Verify permissions default for pharmacist
        perm_profit = await page.is_checked("#perm_can_view_profit")
        perm_stock = await page.is_checked("#perm_can_manage_stock")
        perm_del = await page.is_checked("#perm_can_delete_sales")
        print(f"Pharmacist defaults check -> can_view_profit: {perm_profit} (expect False), can_manage_stock: {perm_stock} (expect True), can_delete_sales: {perm_del} (expect False)")
        assert not perm_profit, "Pharmacist should not have profit view by default"
        assert perm_stock, "Pharmacist should have stock manage by default"
        assert not perm_del, "Pharmacist should not have delete sales by default"

        # Capture screenshot of Modal with Pharmacist & Permissions
        modal_path = os.path.join(SCREENSHOT_DIR, "test_staff_rbac_modal.png")
        await page.screenshot(path=modal_path)
        print(f"✓ Saved modal screenshot: {modal_path}")

        # Fill in new staff info (or update if pharmacist1 exists)
        await page.fill("#staffName", "ເພສັດຊະກອນ ສົມສັກ")
        await page.fill("#staffUsername", "pharmacist1")
        await page.fill("#staffPassword", "pharm123")
        await page.fill("#staffPhone", "020 55667788")

        # Save staff
        await page.click("#saveStaffBtn")
        await asyncio.sleep(1.5)

        # Reload staff page to verify new user
        await page.goto(f"{BASE_URL}/staff.php")
        await page.wait_for_selector("#staffTable")
        table_text = await page.inner_text("#staffTable")
        assert "pharmacist1" in table_text, "pharmacist1 should be in staff table"
        assert "ເພສັດຊະກອນ" in table_text, "Role label ເພສັດຊະກອນ should be present"
        print("✓ New pharmacist user created and verified in staff table.")

        # Capture screenshot of Staff Table with roles
        list_path = os.path.join(SCREENSHOT_DIR, "test_staff_list_roles.png")
        await page.screenshot(path=list_path)
        print(f"✓ Saved staff list screenshot: {list_path}")

        print("=== Step 3: Login as Pharmacist ===")
        # Logout
        await page.goto(f"{BASE_URL}/logout.php")
        await page.wait_for_url("**/login.php")

        # Login as pharmacist1
        await page.fill('input[name="username"]', 'pharmacist1')
        await page.fill('input[name="password"]', 'pharm123')
        await page.click('button[type="submit"]')
        await page.wait_for_load_state("networkidle")
        print(f"Pharmacist landing URL: {page.url}")

        # Verify Sidebar menus for Pharmacist
        sidebar_text = await page.inner_text("#sidebar")
        print("Pharmacist Sidebar items:")
        has_pos = "ໜ້າຂາຍ" in sidebar_text
        has_inv = "ສາງສິນຄ້າ" in sidebar_text
        has_labels = "ບາໂຄດ & ສະຕິກເກີ" in sidebar_text
        has_po = "ໃບສັ່ງຊື້ຢາ" in sidebar_text
        has_profit = "ກຳໄລ-ຂາດທຶນ" in sidebar_text
        has_settings = "ຂໍ້ມູນຮ້ານ" in sidebar_text
        has_staff = "ພະນັກງານ" in sidebar_text

        print(f"  POS: {has_pos} (expect True)")
        print(f"  Inventory: {has_inv} (expect True)")
        print(f"  Barcodes: {has_labels} (expect True)")
        print(f"  PO: {has_po} (expect True)")
        print(f"  Profit/Loss: {has_profit} (expect False)")
        print(f"  Shop Settings: {has_settings} (expect False)")
        print(f"  Staff: {has_staff} (expect False)")

        assert has_pos and has_inv and has_labels and has_po, "Pharmacist must see clinical/inventory tools"
        assert not has_profit, "Pharmacist must NOT see Profit/Loss in sidebar"
        assert not has_settings and not has_staff, "Pharmacist must NOT see Settings or Staff in sidebar"

        # Capture Pharmacist view screenshot
        await page.goto(f"{BASE_URL}/inventory.php")
        await page.wait_for_selector("#productsTable, .table")
        pharm_path = os.path.join(SCREENSHOT_DIR, "test_pharmacist_view.png")
        await page.screenshot(path=pharm_path)
        print(f"✓ Saved pharmacist view screenshot: {pharm_path}")

        # Attempt to directly visit profit_loss_report.php as pharmacist
        response = await page.goto(f"{BASE_URL}/profit_loss_report.php")
        # Should either redirect or return 403
        is_blocked = (response.status == 403) or ("Access Denied" in (await page.content())) or (page.url != f"{BASE_URL}/profit_loss_report.php")
        print(f"Direct access to profit_loss_report.php blocked: {is_blocked} (status: {response.status})")
        assert is_blocked, "Pharmacist should be blocked from profit_loss_report.php"

        print("=== Step 4: Login as Cashier ===")
        await page.goto(f"{BASE_URL}/logout.php")
        await page.wait_for_url("**/login.php")

        await page.fill('input[name="username"]', 'cashier')
        await page.fill('input[name="password"]', 'cashier123')
        await page.click('button[type="submit"]')
        await page.wait_for_url("**/pos.php")
        print("✓ Cashier redirected directly to pos.php upon login.")

        # Check discount input disabled
        discount_disabled = await page.is_disabled("#discountInput")
        print(f"Cashier #discountInput disabled: {discount_disabled} (expect True)")
        assert discount_disabled, "Cashier without can_give_discount must have #discountInput disabled"

        # Check cashier sidebar
        cashier_sidebar = await page.inner_text("#sidebar")
        assert "ສາງສິນຄ້າ" not in cashier_sidebar, "Cashier must not see Inventory"
        assert "ກຳໄລ-ຂາດທຶນ" not in cashier_sidebar, "Cashier must not see Profit/Loss"
        assert "ຈັດການສິນຄ້າ" not in cashier_sidebar, "Cashier must not see Products management"

        cashier_path = os.path.join(SCREENSHOT_DIR, "test_cashier_view.png")
        await page.screenshot(path=cashier_path)
        print(f"✓ Saved cashier view screenshot: {cashier_path}")

        # Attempt direct visit to inventory.php
        await page.goto(f"{BASE_URL}/inventory.php")
        await page.wait_for_load_state("networkidle")
        print(f"Cashier redirected from inventory.php to: {page.url}")
        assert "pos.php" in page.url, "Cashier attempting to visit inventory.php must be redirected to pos.php"

        await browser.close()
        print("\n==========================================")
        print("🎉 ALL RBAC & PHARMACIST ROLE TESTS PASSED!")
        print("==========================================")

if __name__ == "__main__":
    asyncio.run(main())
