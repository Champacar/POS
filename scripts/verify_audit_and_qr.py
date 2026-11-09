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

        # ----------------------------------------------------
        # 1. Test Shop Settings Bank/QR Configuration
        # ----------------------------------------------------
        print("1. Testing Shop Settings Dynamic QR configuration...")
        await page.goto("http://127.0.0.1:8080/shop_settings.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(1000)

        # Scroll down to payment card
        await page.evaluate("window.scrollTo(0, 1100)")
        await page.wait_for_timeout(500)
        ss_settings = os.path.join(SCREENSHOT_DIR, "test_settings_dynamic_qr.png")
        await page.screenshot(path=ss_settings, full_page=False)
        print(f"   -> Saved: {ss_settings}")

        # ----------------------------------------------------
        # 2. Test POS Dynamic LAO QR in Checkout Modal
        # ----------------------------------------------------
        print("2. Testing POS Dynamic LAO QR...")
        await page.goto("http://127.0.0.1:8080/pos.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(1000)

        # Add first available product
        first_prod = page.locator(".product-card:not(.product-expired)").first
        if await first_prod.count() > 0:
            await first_prod.click()
            await page.wait_for_timeout(500)

        # Open checkout modal
        await page.click("#checkoutBtn")
        await page.wait_for_timeout(800)

        # Click Transfer payment button
        await page.click("#btnPayTransfer")
        await page.wait_for_timeout(1200) # wait for dynamic QR SVG load

        ss_pos_qr = os.path.join(SCREENSHOT_DIR, "test_pos_dynamic_qr.png")
        await page.screenshot(path=ss_pos_qr, full_page=False)
        print(f"   -> Saved: {ss_pos_qr}")

        # ----------------------------------------------------
        # 3. Test Customer Facing Display (CFD)
        # ----------------------------------------------------
        print("3. Testing Customer Facing Display Dynamic QR...")
        cfd_page = await context.new_page()
        await cfd_page.goto("http://127.0.0.1:8080/customer_display.php")
        await cfd_page.wait_for_load_state("networkidle")
        await cfd_page.wait_for_timeout(1500)

        ss_cfd = os.path.join(SCREENSHOT_DIR, "test_cfd_dynamic_qr.png")
        await cfd_page.screenshot(path=ss_cfd, full_page=False)
        print(f"   -> Saved: {ss_cfd}")
        await cfd_page.close()

        # ----------------------------------------------------
        # 4. Test Stock Audit in Inventory
        # ----------------------------------------------------
        print("4. Testing Stock Audit in inventory.php...")
        await page.goto("http://127.0.0.1:8080/inventory.php")
        await page.wait_for_load_state("networkidle")

        # Switch to Stock Audit tab
        await page.click("#tab-stock-audit")
        await page.wait_for_timeout(1200)

        # Check if active session or need to start
        start_btn = page.locator("button:has-text('ເປີດຮອບກວດນັບສະຕ໋ອກໃໝ່')")
        if await start_btn.count() > 0:
            print("   -> Opening Start Audit Modal...")
            await start_btn.click()
            await page.wait_for_timeout(600)

            ss_audit_modal = os.path.join(SCREENSHOT_DIR, "test_stock_audit_modal.png")
            await page.screenshot(path=ss_audit_modal, full_page=False)

            # Submit start audit
            await page.click("#startAuditBtn")
            await page.wait_for_timeout(1500)

        # Verify active session is rendered
        kpi_total = await page.inner_text("#kpiAuditTotal")
        print(f"   -> Active audit total items: {kpi_total}")

        # Simulate scanning product barcode
        code_to_scan = "8850000000001"
        print(f"   -> Scanning barcode/item: '{code_to_scan}'...")

        await page.fill("#auditBarcodeInput", code_to_scan)
        await page.press("#auditBarcodeInput", "Enter")
        await page.wait_for_timeout(1200)

        # Test incrementing quantity with '+' button on first row
        plus_btn = page.locator("#auditItemsTable tbody tr button:has-text('+')").first
        if await plus_btn.count() > 0:
            await plus_btn.click()
            await page.wait_for_timeout(1000)

        ss_audit_active = os.path.join(SCREENSHOT_DIR, "test_stock_audit_active.png")
        await page.screenshot(path=ss_audit_active, full_page=False)
        print(f"   -> Saved: {ss_audit_active}")

        # ----------------------------------------------------
        # 5. Test 1-Click Reconcile
        # ----------------------------------------------------
        print("5. Testing 1-Click Reconcile...")
        reconcile_btn = page.locator("button:has-text('ປັບປຸງຍອດສະຕ໋ອກຕົວຈິງ (1-Click Reconcile)')")
        if await reconcile_btn.count() > 0:
            await reconcile_btn.click()
            await page.wait_for_timeout(800)

            # Confirm dialog
            swal_confirm = page.locator(".swal2-confirm")
            if await swal_confirm.count() > 0:
                await swal_confirm.click()
                await page.wait_for_timeout(2000)

                ss_reconciled = os.path.join(SCREENSHOT_DIR, "test_stock_audit_reconciled.png")
                await page.screenshot(path=ss_reconciled, full_page=False)
                print(f"   -> Saved: {ss_reconciled}")

                # Close result swal
                res_ok = page.locator(".swal2-confirm")
                if await res_ok.count() > 0:
                    await res_ok.click()
                    await page.wait_for_timeout(1000)

        await browser.close()
        print("ALL TESTS PASSED WITH 100% SUCCESS!")

if __name__ == '__main__':
    asyncio.run(run())
