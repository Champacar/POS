import urllib.request
import json

req = urllib.request.Request('http://127.0.0.1:8080/api/reorder_suggestions.php?action=expiry_tiers')
req.add_header('Cookie', 'PHPSESSID=fkj3e9r83v30s4q6ggc3hapa78')

try:
    with urllib.request.urlopen(req) as resp:
        body = resp.read().decode('utf-8')
        data = json.loads(body)
        print("Success:", data.get('success'))
        tiers = data.get('tiers', {})
        for k, v in tiers.items():
            print(f"Tier {k}: count={v['count']}, qty={v['total_qty']}, value={v['total_value']}")
except Exception as e:
    print("Error:", e)
