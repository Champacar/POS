import asyncio
from playwright.async_api import async_playwright

async def run():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(viewport={'width': 1280, 'height': 850})
        
        # Add authenticated admin cookie
        await context.add_cookies([{
            'name': 'PHPSESSID',
            'value': 'fkj3e9r83v30s4q6ggc3hapa78',
            'domain': '127.0.0.1',
            'path': '/'
        }])

        page = await context.new_page()

        # Step 1: Navigate to POS
        print("Navigating to POS...")
        await page.goto("http://127.0.0.1:8080/pos.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(500)

        shift_btn = await page.query_selector("#btnShiftStatus")
        assert shift_btn is not None, "Shift status button missing on POS!"
        btn_text = await page.inner_text("#btnShiftStatus")
        print(f"Initial Shift Button text: {btn_text}")

        # If already open, close it first to start a fresh cycle
        if "ປິດກະ" in btn_text:
            print("Existing open shift detected, closing it first...")
            await shift_btn.click()
            await page.wait_for_selector("#closeShiftModal.show", state="visible")
            await page.fill("#inputCountedLak", "1000000")
            await page.click("#btnConfirmCloseShift")
            await page.wait_for_timeout(1500)
            print("Closed previous shift. Refreshing POS...")
            await page.goto("http://127.0.0.1:8080/pos.php")
            await page.wait_for_load_state("networkidle")
            await page.wait_for_timeout(500)

        # Step 2: Open a new shift
        print("Opening a new shift...")
        await page.click("#btnShiftStatus")
        await page.wait_for_selector("#openShiftModal.show", state="visible")
        
        # Enter float: 500,000 LAK
        await page.fill("#inputStartingCash", "500000")
        await page.fill("#inputOpenShiftNote", "ທົດສອບເປີດກະເຊົ້າ")
        await page.screenshot(path="C:/Users/XAI_PC/.gemini/antigravity/brain/cb85b848-6b0f-4cb6-a3cc-6fad0b4b03d1/test_shift_open_modal.png")
        print("Saved test_shift_open_modal.png")

        await page.click("#btnConfirmOpenShift")
        await page.wait_for_timeout(1500)

        new_btn_text = await page.inner_text("#btnShiftStatus")
        print(f"Shift button after opening: {new_btn_text}")
        assert "ປິດກະ" in new_btn_text, "Shift failed to open!"

        # Step 3: Make a sale linked to this shift
        print("Adding product to cart and making sale...")
        cards = await page.query_selector_all(".product-card")
        if len(cards) > 0:
            await cards[0].click()
            await page.click("#checkoutBtn")
            await page.wait_for_selector("#checkoutModal.show", state="visible")
            # Pay exact cash
            await page.click("button:has-text('ພໍດີຍອດຊຳລະ')")
            await page.click("#confirmCheckoutBtn")
            await page.wait_for_timeout(1500)
            # Close receipt modal if open
            close_rcpt = await page.query_selector("#receiptPreviewModal .modal-close")
            if close_rcpt:
                await close_rcpt.click()
            print("Sale completed.")

        # Step 4: Click Shift button to Close Shift
        print("Opening Close Shift modal...")
        await page.click("#btnShiftStatus")
        await page.wait_for_selector("#closeShiftModal.show", state="visible")
        await page.wait_for_timeout(500)

        shift_num = await page.inner_text("#closeShiftNumber")
        bills = await page.inner_text("#closeShiftBills")
        sales_total = await page.inner_text("#closeShiftSalesTotal")
        expected_cash = await page.inner_text("#closeShiftExpectedCash")
        print(f"Close Shift Summary: Shift={shift_num}, Bills={bills}, Sales={sales_total}, Expected={expected_cash}")

        # Step 5: Test cash balancing input
        # Extract numeric expected cash
        expected_num = int("".join([c for c in expected_cash if c.isdigit()]))
        print(f"Expected cash numeric: {expected_num}")

        # Fill exact counted cash
        await page.fill("#inputCountedLak", str(expected_num))
        await page.wait_for_timeout(300)

        diff_status = await page.inner_text("#shiftDiffStatusText")
        print(f"Difference status with exact cash: {diff_status}")
        assert "ພໍດີ" in diff_status, f"Expected balanced status, got: {diff_status}"

        await page.screenshot(path="C:/Users/XAI_PC/.gemini/antigravity/brain/cb85b848-6b0f-4cb6-a3cc-6fad0b4b03d1/test_shift_close_modal.png")
        print("Saved test_shift_close_modal.png")

        # Confirm close shift
        print("Confirming close shift...")
        await page.click("#btnConfirmCloseShift")
        await page.wait_for_timeout(2000)

        # Step 6: Navigate to shifts.php and verify history table
        print("Navigating to shifts.php...")
        await page.goto("http://127.0.0.1:8080/shifts.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(500)

        rows = await page.query_selector_all("#shiftTable tbody tr")
        print(f"Found {len(rows)} shifts in history table")
        assert len(rows) > 0, "No shifts found in history table!"

        await page.screenshot(path="C:/Users/XAI_PC/.gemini/antigravity/brain/cb85b848-6b0f-4cb6-a3cc-6fad0b4b03d1/test_shifts_history_page.png", full_page=True)
        print("Saved test_shifts_history_page.png")

        # Step 7: Capture the Shift Summary Thermal Receipt
        latest_shift_id = await page.evaluate("document.querySelector('#shiftTable tbody tr:first-child button').getAttribute('onclick').match(/\\d+/)[0]")
        print(f"Latest closed shift ID: {latest_shift_id}")

        receipt_page = await browser.new_page(viewport={'width': 420, 'height': 850})
        await receipt_page.goto(f"http://127.0.0.1:8080/api/shift_receipt.php?id={latest_shift_id}")
        await receipt_page.wait_for_load_state("networkidle")
        await receipt_page.screenshot(path="C:/Users/XAI_PC/.gemini/antigravity/brain/cb85b848-6b0f-4cb6-a3cc-6fad0b4b03d1/test_shift_receipt_thermal.png", full_page=True)
        print("Saved test_shift_receipt_thermal.png")

        await browser.close()
        print("All shift lifecycle tests passed with 100% success!")

if __name__ == "__main__":
    asyncio.run(run())
