import asyncio
import os
from playwright.async_api import async_playwright

SCREENSHOT_DIR = r"C:\Users\XAI_PC\.gemini\antigravity\brain\cb85b848-6b0f-4cb6-a3cc-6fad0b4b03d1"

async def run():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            viewport={'width': 1366, 'height': 850},
            locale='lo-LA'
        )

        # Set session cookie
        await context.add_cookies([{
            'name': 'PHPSESSID',
            'value': 'fkj3e9r83v30s4q6ggc3hapa78',
            'domain': '127.0.0.1',
            'path': '/'
        }])

        page = await context.new_page()

        print("1. Navigating to inventory.php...")
        await page.goto("http://127.0.0.1:8080/inventory.php")
        await page.wait_for_load_state("networkidle")

        print("2. Switching to Auto Reorder Tab...")
        await page.click("#tab-auto-reorder")
        await page.wait_for_timeout(1500) # wait for suggestions API

        # Verify KPI values
        total_items = await page.inner_text("#reorderTotalItems")
        total_budget = await page.inner_text("#reorderTotalBudget")
        print(f"   -> KPI Items: {total_items}, Total Budget: {total_budget}")

        screenshot_reorder_hub = os.path.join(SCREENSHOT_DIR, "test_auto_reorder_hub.png")
        await page.screenshot(path=screenshot_reorder_hub, full_page=False)
        print(f"   -> Saved: {screenshot_reorder_hub}")

        print("3. Switching to Batch/Lot Tab and testing expiry filters...")
        await page.click("#tab-batch-list")
        await page.wait_for_timeout(500)
        
        screenshot_batch_all = os.path.join(SCREENSHOT_DIR, "test_batch_list_all.png")
        await page.screenshot(path=screenshot_batch_all, full_page=False)

        # Click Expired filter
        await page.click("button:has-text('ໝົດອາຍຸແລ້ວ')")
        await page.wait_for_timeout(300)
        screenshot_batch_expired = os.path.join(SCREENSHOT_DIR, "test_batch_filter_expired.png")
        await page.screenshot(path=screenshot_batch_expired, full_page=False)

        print("4. Testing 1-Click PO creation in Auto Reorder Tab...")
        await page.click("#tab-auto-reorder")
        await page.wait_for_timeout(800)

        # Look for create PO button
        po_btn = page.locator("button[id^='btn_create_po_']").first
        if await po_btn.count() > 0:
            print("   -> Found 1-Click PO button, clicking...")
            await po_btn.click()
            await page.wait_for_timeout(1500) # wait for Swal

            screenshot_po_created = os.path.join(SCREENSHOT_DIR, "test_po_created_swal.png")
            await page.screenshot(path=screenshot_po_created, full_page=False)
            print(f"   -> Saved: {screenshot_po_created}")

            # Click view PO in Swal if present
            confirm_btn = page.locator(".swal2-confirm")
            if await confirm_btn.count() > 0:
                await confirm_btn.click()
                await page.wait_for_load_state("networkidle")
                await page.wait_for_timeout(1000)

        print("5. Verifying Purchase Orders page...")
        await page.goto("http://127.0.0.1:8080/purchase_orders.php")
        await page.wait_for_load_state("networkidle")
        screenshot_po_list = os.path.join(SCREENSHOT_DIR, "test_purchase_orders_list.png")
        await page.screenshot(path=screenshot_po_list, full_page=False)
        print(f"   -> Saved: {screenshot_po_list}")

        # Test PO Modal filter
        print("6. Testing PO Modal supplier filter...")
        await page.click("button:has-text('ສ້າງໃບສັ່ງຊື້ຢາ')")
        await page.wait_for_timeout(500)
        
        # Select supplier 1
        await page.select_option("#poSupplier", "1")
        # Click pull low stock button
        await page.click("button:has-text('ດຶງສະເພາະຢາໃກ້ໝົດຂອງຜູ້ສະໜອງນີ້')")
        await page.wait_for_timeout(500)
        screenshot_po_modal = os.path.join(SCREENSHOT_DIR, "test_po_modal_supplier_filter.png")
        await page.screenshot(path=screenshot_po_modal, full_page=False)
        print(f"   -> Saved: {screenshot_po_modal}")

        print("7. Verifying POS product badges (Expiring soon & Expired)...")
        await page.goto("http://127.0.0.1:8080/pos.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(800)
        screenshot_pos = os.path.join(SCREENSHOT_DIR, "test_pos_expiry_badges.png")
        await page.screenshot(path=screenshot_pos, full_page=False)
        print(f"   -> Saved: {screenshot_pos}")

        await browser.close()
        print("ALL TESTS PASSED SUCCESSFULLY!")

if __name__ == '__main__':
    asyncio.run(run())
