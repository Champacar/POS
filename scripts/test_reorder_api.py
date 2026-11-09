import urllib.request
import json

req = urllib.request.Request('http://127.0.0.1:8080/api/reorder_suggestions.php?action=suggestions')
req.add_header('Cookie', 'PHPSESSID=fkj3e9r83v30s4q6ggc3hapa78')

try:
    with urllib.request.urlopen(req) as resp:
        body = resp.read().decode('utf-8')
        data = json.loads(body)
        print("Success:", data.get('success'))
        summary = data.get('summary', {})
        print("Summary:", summary)
        suppliers = data.get('suppliers', [])
        print(f"Total suppliers with low stock: {len(suppliers)}")
        for g in suppliers:
            print(f" - Supplier: {g['supplier_name']} (ID: {g['supplier_id']}), Items: {g['total_items']}, Cost: {g['total_cost']}")
            for it in g['items'][:2]:
                print(f"    * {it['name']}: stock={it['stock_quantity']}/{it['min_stock']}, suggest={it['suggested_quantity']}, cost={it['cost_price']}")
except Exception as e:
    print("Error:", e)
