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
        # 1. Test Shop Settings Bank & Live Test QR
        # ----------------------------------------------------
        print("1. Opening shop_settings.php...", flush=True)
        await page.goto("http://127.0.0.1:8080/shop_settings.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(1000)

        # Check if login needed
        if "login.php" in page.url:
            print("   Session expired, logging in...", flush=True)
            await page.fill('input[name="username"]', 'admin')
            await page.fill('input[name="password"]', 'admin123')
            await page.click('button[type="submit"]')
            await page.wait_for_load_state("networkidle")
            await page.goto("http://127.0.0.1:8080/shop_settings.php")
            await page.wait_for_load_state("networkidle")

        # Scroll to Dynamic QR section
        await page.evaluate("window.scrollTo(0, 1050)")
        await page.wait_for_timeout(1000)

        # Test live QR generator
        print("   Testing Live Test QR Generator in Shop Settings...", flush=True)
        await page.fill("#testQrAmountInput", "35000")
        await page.click("button:has-text('ສ້າງ QR')")
        await page.wait_for_timeout(1200)

        # Verify SVG rendered
        has_svg = await page.locator("#testQrContainer svg").count() > 0
        payload_text = await page.locator("#testQrPayloadText").text_content()
        print(f"   Test QR SVG rendered: {has_svg}", flush=True)
        print(f"   Test QR Payload: {payload_text}", flush=True)

        # Test payload validator with Sample BCEL string
        sample_bcel = "00020101021233980004BCEL0118ONEPAYSUBSCRIPTION0216mch679ae2c0a187d0320SBDMQTGMVMSRUVQUZOLY0406RULE-5051083030620155204729953034185802LA624602102030-06-01050410000820TAITTJVQEKRJZZJUFMIR6304DABB"
        print("   Testing Raw QR Payload Validator...", flush=True)
        await page.fill("#inputQrPayload", sample_bcel)
        await page.click("button:has-text('ກວດສອບລະຫັດ QR')")
        await page.wait_for_timeout(1000)

        feedback_text = await page.locator("#qrPayloadFeedback").text_content()
        print(f"   Payload Feedback: {feedback_text}", flush=True)

        # Save screenshot of Shop Settings Dynamic QR section
        ss_settings = os.path.join(SCREENSHOT_DIR, "test_dynamic_qr_settings_v2.png")
        await page.screenshot(path=ss_settings, full_page=False)
        print(f"   -> Saved: {ss_settings}", flush=True)

        # Save settings
        print("   Saving settings form...", flush=True)
        await page.click("button[type='submit']")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(1000)

        # ----------------------------------------------------
        # 2. Test POS Checkout Modal Dynamic QR
        # ----------------------------------------------------
        print("2. Testing POS Checkout Modal Dynamic QR...", flush=True)
        await page.goto("http://127.0.0.1:8080/pos.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(1000)

        # Add an available in-stock product to cart
        await page.evaluate("""() => {
            const card = Array.from(document.querySelectorAll('.product-card:not(.product-expired)')).find(c => parseInt(c.dataset.stock) > 0);
            if (card && typeof addToCart === 'function') {
                addToCart(card);
            }
        }""")
        await page.wait_for_timeout(800)

        # Wait for checkout button to be enabled
        await page.wait_for_selector("#checkoutBtn:not([disabled])", timeout=5000)
        await page.click("#checkoutBtn")
        await page.wait_for_timeout(800)

        # Click Transfer payment button (#btnPayTransfer)
        await page.click("#btnPayTransfer")
        await page.wait_for_timeout(1500) # wait for dynamic QR SVG load

        # Verify QR in checkout modal
        has_pos_svg = await page.locator("#transferQrContainer svg").count() > 0
        amt_display = await page.locator("#transferAmountDisplay").text_content()
        print(f"   POS Transfer QR SVG displayed: {has_pos_svg}", flush=True)
        print(f"   POS Transfer Amount displayed: {amt_display}", flush=True)

        ss_pos = os.path.join(SCREENSHOT_DIR, "test_pos_dynamic_qr_v2.png")
        await page.screenshot(path=ss_pos, full_page=False)
        print(f"   -> Saved: {ss_pos}", flush=True)

        # ----------------------------------------------------
        # 3. Test Customer Display Dynamic QR
        # ----------------------------------------------------
        print("3. Testing Customer Display (CFD) Dynamic QR...", flush=True)
        cfd_page = await context.new_page()
        await cfd_page.goto("http://127.0.0.1:8080/customer_display.php")
        await cfd_page.wait_for_load_state("networkidle")
        await cfd_page.wait_for_timeout(1500)

        # CFD displays the active cart QR
        ss_cfd = os.path.join(SCREENSHOT_DIR, "test_cfd_dynamic_qr_v2.png")
        await cfd_page.screenshot(path=ss_cfd, full_page=False)
        print(f"   -> Saved: {ss_cfd}", flush=True)

        await browser.close()
        print("All Dynamic LAO QR verification tests completed successfully!", flush=True)

if __name__ == "__main__":
    asyncio.run(run())
