import asyncio
from playwright.async_api import async_playwright

async def test():
    async with async_playwright() as p:
        browser = await p.chromium.launch()
        page = await browser.new_page()
        has_bd = await page.evaluate("() => 'BarcodeDetector' in window")
        print('Native BarcodeDetector in Chromium:', has_bd)
        if has_bd:
            formats = await page.evaluate("async () => await BarcodeDetector.getSupportedFormats()")
            print('Supported formats:', formats)
        await browser.close()

if __name__ == "__main__":
    asyncio.run(test())
