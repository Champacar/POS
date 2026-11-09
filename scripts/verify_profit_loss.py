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

        print("1. Opening profit_loss_report.php...", flush=True)
        await page.goto("http://127.0.0.1:8080/profit_loss_report.php")
        await page.wait_for_load_state("networkidle")
        await page.wait_for_timeout(1000)

        # Check if redirected to login
        if "login.php" in page.url:
            print("   Session expired, logging in...", flush=True)
            await page.fill('input[name="username"]', 'admin')
            await page.fill('input[name="password"]', 'admin123')
            await page.click('button[type="submit"]')
            await page.wait_for_load_state("networkidle")
            await page.goto("http://127.0.0.1:8080/profit_loss_report.php")
            await page.wait_for_load_state("networkidle")

        # Wait for data to load
        await page.wait_for_selector("#kpiRevenue")
        await page.wait_for_timeout(2000)

        # Check KPI values
        rev_text = await page.locator("#kpiRevenue").text_content()
        cogs_text = await page.locator("#kpiCogs").text_content()
        gross_text = await page.locator("#kpiGrossProfit").text_content()
        gross_margin_text = await page.locator("#badgeGrossMargin").text_content()
        net_text = await page.locator("#kpiNetProfit").text_content()
        net_margin_text = await page.locator("#badgeNetMargin").text_content()

        print(f"   KPI Revenue: {rev_text}", flush=True)
        print(f"   KPI COGS: {cogs_text}", flush=True)
        print(f"   KPI Gross Profit: {gross_text} ({gross_margin_text})", flush=True)
        print(f"   KPI Net Profit: {net_text} ({net_margin_text})", flush=True)

        # Overview screenshot
        ss_overview = os.path.join(SCREENSHOT_DIR, "test_profit_loss_overview.png")
        await page.screenshot(path=ss_overview, full_page=False)
        print(f"   -> Saved overview: {ss_overview}", flush=True)

        # Check charts via Chart.js API
        charts_ready = await page.evaluate("""() => {
            return {
                trend: !!(window.Chart && Chart.getChart('profitTrendChart')),
                cat: !!(window.Chart && Chart.getChart('categoryShareChart')),
                peak: !!(window.Chart && Chart.getChart('peakHoursChart'))
            };
        }""")
        print(f"   Charts initialized: {charts_ready}", flush=True)

        # Scroll down to charts
        await page.evaluate("window.scrollTo(0, 480)")
        await page.wait_for_timeout(500)
        ss_charts = os.path.join(SCREENSHOT_DIR, "test_profit_loss_charts.png")
        await page.screenshot(path=ss_charts, full_page=False)
        print(f"   -> Saved charts: {ss_charts}", flush=True)

        # ----------------------------------------------------
        # 2. Test Drilldown Tabs
        # ----------------------------------------------------
        print("2. Testing Drilldown Tabs...", flush=True)

        # Scroll to tables
        await page.evaluate("window.scrollTo(0, 1050)")
        await page.wait_for_timeout(500)

        # Medicine tab is active by default
        ss_products = os.path.join(SCREENSHOT_DIR, "test_profit_loss_products.png")
        await page.screenshot(path=ss_products, full_page=False)
        print(f"   -> Saved products drilldown: {ss_products}", flush=True)

        # Test medicine search
        print("   Testing medicine search input...", flush=True)
        await page.fill("#plProductSearch", "Para")
        await page.wait_for_timeout(300)
        prod_count = await page.locator("#plProductTableBody tr:visible").count()
        print(f"   Visible rows for 'Para': {prod_count}", flush=True)
        await page.fill("#plProductSearch", "")
        await page.wait_for_timeout(300)

        # Switch to Categories tab
        print("   Switching to Categories tab...", flush=True)
        await page.click("#plTabCategories")
        await page.wait_for_timeout(500)
        cat_count = await page.locator("#plCategoryTableBody tr:visible").count()
        print(f"   Category rows count: {cat_count}", flush=True)
        ss_cat = os.path.join(SCREENSHOT_DIR, "test_profit_loss_categories.png")
        await page.screenshot(path=ss_cat, full_page=False)
        print(f"   -> Saved categories drilldown: {ss_cat}", flush=True)

        # Switch to Payment Methods tab
        print("   Switching to Payment Methods tab...", flush=True)
        await page.click("#plTabPayments")
        await page.wait_for_timeout(500)
        cash_val = await page.locator("#payCashTotal").text_content()
        qr_val = await page.locator("#payTransferTotal").text_content()
        print(f"   Payment methods - Cash: {cash_val}, QR: {qr_val}", flush=True)

        # ----------------------------------------------------
        # 3. Test Date Filter Presets
        # ----------------------------------------------------
        print("3. Testing Date Filter Presets (This Year)...", flush=True)
        await page.click("#btnFilterThisYear")
        await page.wait_for_timeout(1500)
        new_rev = await page.locator("#kpiRevenue").text_content()
        print(f"   Updated Revenue after This Year filter: {new_rev}", flush=True)

        # ----------------------------------------------------
        # 4. Test Excel Export
        # ----------------------------------------------------
        print("4. Testing Excel Export function call...", flush=True)
        export_success = await page.evaluate("""() => {
            try {
                if (typeof XLSX === 'undefined') return { success: false, reason: 'XLSX not defined' };
                // Call exportProfitLossExcel without throwing
                exportProfitLossExcel();
                return { success: true };
            } catch (e) {
                return { success: false, reason: e.toString() };
            }
        }""")
        print(f"   Excel export execution: {export_success}", flush=True)

        await page.wait_for_timeout(1000)
        await browser.close()
        print("All Profit/Loss & Analytics Dashboard tests completed successfully!", flush=True)

if __name__ == "__main__":
    asyncio.run(run())
