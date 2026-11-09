import urllib.request
import json

req = urllib.request.Request('http://127.0.0.1:8080/api/profit_loss.php?start=2026-01-01&end=2026-12-31')
req.add_header('Cookie', 'PHPSESSID=fkj3e9r83v30s4q6ggc3hapa78')

try:
    with urllib.request.urlopen(req) as resp:
        data = json.loads(resp.read().decode('utf-8'))
        print("Success:", data.get('success'))
        kpis = data.get('kpis', {})
        print("Revenue:", kpis.get('total_revenue'))
        print("COGS:", kpis.get('total_cogs'))
        print("Gross Profit:", kpis.get('gross_profit'), f"({kpis.get('gross_margin')}%)")
        print("Expenses:", kpis.get('operating_expenses'))
        print("Net Profit:", kpis.get('net_profit'), f"({kpis.get('net_margin')}%)")
        print("Categories count:", len(data.get('categories', [])))
        print("Top products count:", len(data.get('products', [])))
        if data.get('products'):
            top = data['products'][0]
            print(f"Top profit item: {top['product_name']} (Profit: {top['total_profit']} LAK, Margin: {top['margin_percent']}%)")
        print("Peak hours count:", len(data.get('peak_hours', [])))
except Exception as e:
    print("Error:", e)
