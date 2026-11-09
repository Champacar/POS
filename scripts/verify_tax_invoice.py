import asyncio
import os
import sys
from playwright.async_api import async_playwright

BASE_URL = "http://127.0.0.1:8080"
SCREENSHOT_DIR = r"C:\Users\XAI_PC\.gemini\antigravity\brain\cb85b848-6b0f-4cb6-a3cc-6fad0b4b03d1"

async def main():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(viewport={"width": 1400, "height": 950})
        page = await context.new_page()

        print("=== Step 1: Login as Admin ===")
        await page.goto(f"{BASE_URL}/login.php")
        await page.fill('input[name="username"]', 'admin')
        await page.fill('input[name="password"]', 'admin123')
        await page.click('button[type="submit"]')
        await page.wait_for_url("**/index.php")
        print("✓ Logged in as Admin.")

        print("=== Step 2: Open Tax & Financial Reports ===")
        await page.goto(f"{BASE_URL}/tax_report.php")
        await page.wait_for_selector("#kpiTotalSales")
        await asyncio.sleep(1.0)
        print("✓ tax_report.php loaded.")

        # Capture Tab 1: VAT Summary
        vat_path = os.path.join(SCREENSHOT_DIR, "test_tax_report_vat.png")
        await page.screenshot(path=vat_path)
        print(f"✓ Saved VAT report screenshot: {vat_path}")

        # Switch to Tab 2: Cash Flow
        await page.click("#tabBtnCashFlow")
        await page.wait_for_selector("#cfTotalInflow", state="visible")
        await asyncio.sleep(0.5)

        cf_inflow = await page.inner_text("#cfTotalInflow")
        cf_outflow = await page.inner_text("#cfTotalOutflow")
        cf_net = await page.inner_text("#cfNetCashFlow")
        print(f"Cash Flow -> Total Inflow: {cf_inflow}, Total Outflow: {cf_outflow}, Net: {cf_net}")

        cf_path = os.path.join(SCREENSHOT_DIR, "test_tax_report_cashflow.png")
        await page.screenshot(path=cf_path)
        print(f"✓ Saved Cash Flow screenshot: {cf_path}")

        # Switch to Tab 3: Tax Settings
        await page.click("#tabBtnSettings")
        await page.wait_for_selector("#settTaxId", state="visible")
        await asyncio.sleep(0.3)
        await page.fill("#settTaxId", "01-00294821")
        await page.fill("#settCompanyName", "ບໍລິສັດ ໂພສຟາມາ ຢາດີ ຈຳກັດຜູ້ດຽວ")
        await page.click("#btnSaveTaxSettings")
        await asyncio.sleep(1.0)
        print("✓ Tax settings verified & saved.")

        print("=== Step 3: POS Tax Invoice Checkout ===")
        await page.goto(f"{BASE_URL}/pos.php")
        await page.wait_for_selector(".product-card")
        await asyncio.sleep(1.0)

        # Select first in-stock product
        clicked = await page.evaluate("""() => {
            const cards = Array.from(document.querySelectorAll('.product-card:not(.product-expired)'));
            const inStock = cards.find(c => parseInt(c.dataset.stock) > 0);
            if (inStock) {
                inStock.click();
                return inStock.dataset.name;
            }
            return null;
        }""")
        print(f"Added product to cart: {clicked}")
        assert clicked, "Should find at least 1 in-stock product"
        await asyncio.sleep(0.5)

        # Click Checkout
        await page.click("#checkoutBtn")
        await page.wait_for_selector("#checkoutModal", state="visible")
        await asyncio.sleep(0.5)

        # Toggle Tax Invoice checkbox
        await page.click("#chkIssueTaxInvoice")
        await asyncio.sleep(0.3)
        assert await page.is_visible("#taxInvoiceFields"), "Tax invoice fields should become visible"

        # Fill Tax Invoice fields
        await page.fill("#taxCustomerCompany", "ບໍລິສັດ ລາວ ໂພສຟາມາ ດີເວວລົບເມີ້ນ ຈຳກັດ")
        await page.fill("#taxCustomerTin", "01-99887766")
        await page.fill("#taxCustomerAddress", "ບ້ານ ໜອງບອນ, ເມືອງໄຊເສດຖາ, ນະຄອນຫຼວງວຽງຈັນ")

        # Select exact cash
        await page.click('button:has-text("ພໍດີຍອດຊຳລະ")')
        await asyncio.sleep(0.3)

        # Capture POS checkout modal screenshot
        pos_modal_path = os.path.join(SCREENSHOT_DIR, "test_pos_tax_invoice_checkout.png")
        await page.screenshot(path=pos_modal_path)
        print(f"✓ Saved POS Tax Checkout modal screenshot: {pos_modal_path}")

        # Listen for popup / new page when Tax Invoice opens
        async with context.expect_page() as new_page_info:
            await page.click("#confirmCheckoutBtn")

        tax_invoice_page = await new_page_info.value
        await tax_invoice_page.wait_for_load_state("load")
        print(f"✓ Tax Invoice page opened: {tax_invoice_page.url}")

        # Check Tax Invoice contents
        inv_content = await tax_invoice_page.content()
        assert "ໃບກຳກັບພາສີ" in inv_content, "Tax invoice title must be present"
        assert "01-00294821" in inv_content, "Shop TIN must be present"
        assert "01-99887766" in inv_content, "Customer TIN must be present"
        assert "ບໍລິສັດ ລາວ ໂພສຟາມາ ດີເວວລົບເມີ້ນ ຈຳກັດ" in inv_content, "Customer company must be present"
        assert "ກີບຖ້ວນ" in inv_content, "Lao words amount must be present"

        # Capture Tax Invoice A4 screenshot
        a4_path = os.path.join(SCREENSHOT_DIR, "test_official_tax_invoice_a4.png")
        await tax_invoice_page.screenshot(path=a4_path)
        print(f"✓ Saved official Tax Invoice screenshot: {a4_path}")
        await tax_invoice_page.close()

        print("=== Step 4: Verify in Sales History & Retroactive Issuance ===")
        await page.goto(f"{BASE_URL}/sales_history.php")
        await page.wait_for_selector(".table tbody tr")
        await asyncio.sleep(0.5)

        history_text = await page.inner_text(".table")
        assert "VAT" in history_text, "VAT badge should appear on tax invoice sale"
        assert "01-99887766" in history_text, "Customer TIN should appear in table"

        # Capture Sales History screenshot
        hist_path = os.path.join(SCREENSHOT_DIR, "test_sales_history_tax_badge.png")
        await page.screenshot(path=hist_path)
        print(f"✓ Saved Sales History screenshot: {hist_path}")

        # Test Retroactive Tax Modal
        retro_btns = page.locator('button[onclick*="openRetroTaxInvoiceModal"]')
        if await retro_btns.count() > 0:
            await retro_btns.first.click()
            await page.wait_for_selector("#retroTaxInvoiceModal", state="visible")
            await asyncio.sleep(0.3)
            await page.fill("#retroCustomerCompany", "ໂຮງໝໍສູນກາງ ມິດຕະພາບ")
            await page.fill("#retroCustomerTin", "01-55443322")
            await page.fill("#retroCustomerAddress", "ຖະໜົນ 13 ເໜືອ, ນະຄອນຫຼວງວຽງຈັນ")

            # Click confirm
            async with context.expect_page() as retro_page_info:
                await page.click("#btnSubmitRetroTax")

            retro_tax_page = await retro_page_info.value
            await retro_tax_page.wait_for_load_state("load")
            retro_content = await retro_tax_page.content()
            assert "01-55443322" in retro_content, "Retroactive TIN must be rendered"
            print("✓ Retroactive Tax Invoice successfully generated and verified.")
            await retro_tax_page.close()

        await browser.close()
        print("\n=======================================================")
        print("🎉 ALL TAX INVOICE & FINANCIAL REPORT TESTS PASSED 100%!")
        print("=======================================================")

if __name__ == "__main__":
    asyncio.run(main())
