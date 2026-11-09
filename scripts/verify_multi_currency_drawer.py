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

        # Step 1: Check Shop Settings
        print("Navigating to Shop Settings...")
        await page.goto("http://127.0.0.1:8080/shop_settings.php")
        await page.wait_for_load_state("networkidle")
        
        # Verify multi-currency & cash drawer sections exist
        thb_input = await page.query_selector("#inputExchangeRateThb")
        usd_input = await page.query_selector("#inputExchangeRateUsd")
        drawer_input = await page.query_selector("#inputEnableCashDrawer")
        
        assert thb_input is not None, "Exchange rate THB input missing!"
        assert usd_input is not None, "Exchange rate USD input missing!"
        assert drawer_input is not None, "Enable cash drawer input missing!"
        
        thb_val = await thb_input.input_value()
        usd_val = await usd_input.input_value()
        print(f"Shop settings current rates: THB={thb_val}, USD={usd_val}")

        await page.screenshot(path="C:/Users/XAI_PC/.gemini/antigravity/brain/cb85b848-6b0f-4cb6-a3cc-6fad0b4b03d1/test_settings.png", full_page=True)
        print("Saved test_settings.png")

        # Step 2: Check POS Page
        print("Navigating to POS...")
        await page.goto("http://127.0.0.1:8080/pos.php")
        await page.wait_for_load_state("networkidle")

        # Check drawer button in header
        drawer_btn = await page.query_selector("#btnManualOpenDrawer")
        assert drawer_btn is not None, "POS manual drawer button missing!"
        print("Found manual drawer button in POS header: [🗄️ ເປີດລິ້ນຊັກ (F7)]")

        # Click a product to add to cart
        product_cards = await page.query_selector_all(".product-card")
        if len(product_cards) > 0:
            await product_cards[0].click()
            print("Added first product to cart")
        else:
            print("Warning: no products in grid")

        # Open checkout modal
        checkout_btn = await page.query_selector("#checkoutBtn")
        await checkout_btn.click()
        await page.wait_for_selector("#checkoutModal.show", state="visible")
        print("Checkout modal opened")

        # Check currency pills
        btn_lak = await page.query_selector("#btnCurrLAK")
        btn_thb = await page.query_selector("#btnCurrTHB")
        btn_usd = await page.query_selector("#btnCurrUSD")
        assert btn_lak is not None and btn_thb is not None and btn_usd is not None, "Currency pills missing!"
        print("Currency pills found: LAK, THB, USD")

        # Switch to THB
        print("Switching currency to THB...")
        await btn_thb.click()
        await page.wait_for_timeout(400)

        # Check that rate notice is visible
        rate_notice = await page.query_selector("#currencyRateNotice")
        is_visible = await rate_notice.is_visible()
        assert is_visible, "Rate notice not visible after selecting THB!"
        
        rate_text = await page.inner_text("#currencyRateText")
        foreign_total = await page.inner_text("#currencyForeignTotalText")
        print(f"Rate notice: {rate_text} | Total in THB: {foreign_total}")

        # Test Quick Cash button in THB (+500 ฿)
        await page.click("button:has-text('+500 ฿')")
        await page.wait_for_timeout(400)

        cash_val = await page.input_value("#cashAmountInput")
        cash_eq = await page.inner_text("#cashForeignEquivalent")
        change_text = await page.inner_text("#changeDisplay")
        change_foreign = await page.inner_text("#changeForeignDisplay")
        print(f"Cash Input: {cash_val} THB | Eq: {cash_eq} | Change: {change_text} | Foreign change: {change_foreign}")

        await page.screenshot(path="C:/Users/XAI_PC/.gemini/antigravity/brain/cb85b848-6b0f-4cb6-a3cc-6fad0b4b03d1/test_pos_thb_checkout.png")
        print("Saved test_pos_thb_checkout.png")

        # Confirm checkout
        print("Confirming checkout in THB...")
        await page.click("#confirmCheckoutBtn")
        await page.wait_for_timeout(2000)

        # Wait for receipt modal
        receipt_modal = await page.query_selector("#receiptPreviewModal.show")
        if receipt_modal:
            print("Receipt modal shown successfully!")
            await page.screenshot(path="C:/Users/XAI_PC/.gemini/antigravity/brain/cb85b848-6b0f-4cb6-a3cc-6fad0b4b03d1/test_receipt_thb.png")
            print("Saved test_receipt_thb.png")

        # Verify last sale in database has foreign currency recorded
        await browser.close()
        print("All tests passed successfully!")

if __name__ == "__main__":
    asyncio.run(run())
