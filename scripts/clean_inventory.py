with open('inventory.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()

new_lines = []
skip = False
in_tab1_extra = False
in_tab2_extra = False

for i, line in enumerate(lines):
    # Check if we hit extra tab 4 before Tab 2
    if '<!-- Tab 4: Auto Reorder Suggestions -->' in line and i < 400:
        skip = True
        continue
    if skip:
        if '<!-- Tab 2: Stock History Logs -->' in line or '<!-- Tab 3: Lot/Batch List -->' in line:
            skip = False
            new_lines.append(line)
        continue
    new_lines.append(line)

with open('inventory.php', 'w', encoding='utf-8') as f:
    f.writelines(new_lines)

print(f"Cleaned inventory.php. New line count: {len(new_lines)}")
