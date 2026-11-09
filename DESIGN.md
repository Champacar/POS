# 🌿 POS Pharmacy - Design System Guidelines (DESIGN.md)

## 1. Color Palette (ລະບົບສີມາດຕະຖານ)
### Brand Colors
- **Primary (`--primary`):** `#2e7d32` (Material Green 800 - ສີຫຼັກຂອງລະບົບຮ້ານຢາ)
- **Primary Light (`--primary-light`):** `#4caf50` (Green 500 - Hover / Interactive)
- **Primary Dark (`--primary-dark`):** `#1b5e20` (Green 900 - Active / Header)
- **Primary Surface (`--primary-bg`):** `#e8f5e9` (Green 50 - ພື້ນຫຼັງ Badge, Icon container)

### Neutral & Background Colors
- **App Gradient Background:** `linear-gradient(135deg, #b2dfdb 0%, #c8e6c9 40%, #dcedc8 100%)`
- **Content Area Surface (`--bg-content`):** `rgba(200, 235, 210, 0.45)` (Glassmorphism card base)
- **Card Surface (`--card-bg`):** `#ffffff` (Pure White)
- **Border Color (`--border-color`):** `#e0e0e0`
- **Border Light (`--border-light`):** `rgba(0, 0, 0, 0.06)`

### Semantic & Status Colors
- **Success (`--success`):** `#2e7d32` (Surface: `#e8f5e9`, Border: `#c8e6c9`)
- **Warning (`--warning`):** `#935100` (Surface: `#fff3e0`, Border: `#ffe082` - ປ້າຍເຕືອນໃກ້ໝົດອາຍຸ Contrast 5.78:1 ຜ່ານເກນ WCAG AA)
- **Danger (`--danger`):** `#d32f2f` (Surface: `#ffebee`, Border: `#ffcdd2` - ສິນຄ້າໝົດອາຍຸ, ປຸ່ມລຶບ)
- **Info (`--info`):** `#0277bd` (Surface: `#e1f5fe`, Border: `#b3e5fc`)
- **Accent Coral (`--accent-coral`):** `#e57373` (Surface: `#ffebee` - ຍອດເງິນໂອນ, ແຈ້ງເຕືອນດ່ວນ)

### Neutral & Text Colors (ຜ່ານເກນ WCAG AA 4.5:1)
- **Text Primary (`--text-dark`):** `#263238` (Blue Grey 900 - ຫົວຂໍ້, ຂໍ້ຄວາມຫຼັກ - Contrast > 12:1)
- **Text Secondary (`--text-medium`):** `#455a64` (Blue Grey 700 - ຄຳອະທິບາຍ, Label - Contrast > 7:1)
- **Text Muted (`--text-light`):** `#546e7a` (Blue Grey 600 - Placeholder, ຂໍ້ຄວາມຮອງ - Contrast 5.77:1)

---

## 2. Typography Scale (ຂະໜາດຕົວອັກສອນ Noto Sans Lao)
- **Font Family:** `'Noto Sans Lao', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif`
- **Root Font Size:** `87.5%` (ທຽບເທົ່າ 14px ແຕ່ຮອງຮັບ Accessibility Text Scaling ຂອງ OS)
- **Line Heights:** Heading `1.25`, Body `1.5 - 1.6` (ຮອງຮັບສະຫຼະ ແລະ ວັນນະຍຸດພາສາລາວ 3 ລະດັບ)
- **Type Scale:**
  - `--font-xs`: `0.8rem` (11.2px - ສະເພາະ Badge/Tag ນ້ອຍ ຫ້າມໃຊ້ກັບເນື້ອຫາຫຼັກ)
  - `--font-sm`: `0.85rem` (12px - ຕາຕະລາງ, Label, ຂໍ້ຄວາມຊ່ວຍເຫຼືອ)
  - `--font-base`: `0.95rem` (13.3px - ເນື້ອຫາຫຼັກ, ປຸ່ມກົດ, Input)
  - `--font-md`: `1.1rem` (15.4px - ຫົວຂໍ້ກາດ, Modal Title)
  - `--font-lg`: `1.25rem` (17.5px - ຫົວຂໍ້ Section)
  - `--font-xl`: `1.5rem` (21px - Page Title)
  - `--font-2xl`: `1.75rem` (24.5px - ຍອດເງິນລວມ POS, KPI Metrics)

---

## 3. Button Hierarchy (ລຳດັບຄວາມສຳຄັນຂອງປຸ່ມ)
1. **Primary Button (`.btn-primary`):** Action ຫຼັກ (ບັນທຶກ, ຊຳລະເງິນ, ເພີ່ມສິນຄ້າ) - ສີຂຽວ `#2e7d32`
2. **Secondary / Neutral Button (`.btn-secondary` ຫຼື `.btn-white`):** Action ຮອງ (ຍົກເລີກ, ສົ່ງອອກ Excel) - ສີຂາວ ຂອບ `#e0e0e0`
3. **Outline Button (`.btn-outline`):** Filter, ຕົວເລືອກຮູບແບບການຈ່າຍ - ຂອບ `#e0e0e0`, ເມື່ອ Active ເປັນສີຂຽວ
4. **Danger Button (`.btn-danger`):** Action ອັນຕະລາຍ (ລຶບສິນຄ້າ, ຍົກເລີກບິນ, ລ້າງກະຕ່າ) - ສີແດງ `#d32f2f`
5. **Disabled Button State:** Background `#e0e0e0`, Text `#616161`, Cursor `not-allowed`, Opacity `1`

---

## 4. Spacing & Border Radius (ໄລຍະຫ່າງ ແລະ ມຸມໂຄ້ງ)
### Spacing Scale
- `4px`, `8px`, `12px`, `16px` (ມາດຕະຖານ Gap / Card Inner), `20px`, `24px` (Page Padding), `32px`

### Border Radius Scale
- **Small:** `6px` (Badge, Tag, Checkbox)
- **Medium:** `8px - 10px` (Button, Form Input, Date Filter)
- **Large:** `14px - 16px` (Main Card, Stat Card, Modal)
- **Pill:** `9999px` (Pill Tabs, Rounded Status)

---

## 5. Accessibility, Mobile & Interactive States
- **Focus Indicator:** `:focus-visible { outline: 2px solid var(--primary) !important; outline-offset: 2px !important; }`
- **Disabled State:** `.btn:disabled, .btn[disabled], button:disabled { opacity: 0.65 !important; cursor: not-allowed !important; transform: none !important; }` (ຫ້າມໃຊ້ `pointer-events: none` ເພື່ອໃຫ້ສະແດງເຄີເຊີຮູບຫ້າມ ແລະ Tooltip)
- **Active State:** `.btn:active { transform: translateY(1px) scale(0.98); }`
- **Responsive Tables:** `.table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }`
- **Touch Target:** ປຸ່ມສຳຄັນເທິງຈໍສຳຜັດ ແລະ Mobile ຕ້ອງມີຂະໜາດຢ່າງໜ້ອຍ `40px - 44px` (ປຸ່ມ `.btn-sm` ເທິງ Mobile ຕັ້ງ `min-height: 42px`)
- **Toast Position:** ເທິງ Mobile (`max-width: 991px`) Toast ຕ້ອງຢູ່ດ້ານເທິງ `top: 75px` ເພື່ອບໍ່ບັງ Bottom Navigation Bar
