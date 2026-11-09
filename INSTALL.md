# 📖 ຄູ່ມືການນຳໃຊ້ ແລະ ການຕິດຕັ້ງ (User & Installation Manual)
## ລະບົບຂາຍຢາດີ - POS Pharmacy

ຄູ່ມືນີ້ປະກອບມີສອງສ່ວນຫຼັກ: **ຄູ່ມືການຕິດຕັ້ງ ແລະ ຕັ້ງຄ່າ (Installation & Config Guide)** ສຳລັບຜູ້ດູແລລະບົບ (IT/Admin) ແລະ **ຄູ່ມືການນຳໃຊ້ (User Manual)** ສຳລັບພະນັກງານຂາຍ ແລະ ຜູ້ຈັດການຮ້ານ.

---

# 🇱🇦 ພາກທີ 1: ຄູ່ມືການຕິດຕັ້ງ ແລະ ການຕັ້ງຄ່າ (IT/Admin Guide)

## 1. ຄວາມຕ້ອງການຂອງລະບົບ (System Requirements)
* **OS**: Linux (Ubuntu/Debian, CentOS), Windows (XAMPP/WAMP), ຫຼື Cloud Hostings (cPanel, DirectAdmin)
* **Web Server**: Apache 2.4+ (ຮອງຮັບການໃຊ້ `.htaccess` ແລະ `mod_rewrite`)
* **PHP Version**: PHP 7.4 ຫາ PHP 8.2+
* **Database**: MySQL 5.7+ ຫຼື MariaDB 10.3+
* **PHP Extensions ທີ່ຈຳເປັນ**: `PDO`, `pdo_mysql`, `mbstring`, `json`, `session`, `fileinfo`

---

## 2. ຂັ້ນຕອນການຕິດຕັ້ງ (Step-by-Step Installation)

### ຂັ້ນຕອນທີ 2.1: Upload File ລະບົບ
1. ທຳການ Copy ໄຟລ໌ໂຄງການທັງໝົດໄປໄວ້ທີ່ Folder ຂອງ Web Server:
   * **XAMPP (Windows)**: `C:\xampp\htdocs\pospharma`
   * **VPS / Linux Apache**: `/var/www/html/pospharma`
   * **cPanel / Shared Hosting**: Upload ໄຟລ໌ `.zip` ຂຶ້ນໄປທີ່ `public_html/` ຫຼື sub-directory ທີ່ຕ້ອງການ ແລ້ວແຕກໄຟລ໌.

### ຂັ້ນຕອນທີ 2.2: ຕັ້ງຄ່າສິດການເຂົ້າເຖິງ File (Permissions)
ສຳລັບ Linux Server (VPS/Cloud) ໃຫ້ຕັ້ງຄ່າ Permissions ໃຫ້ Folder ທີ່ຕ້ອງມີການຂຽນໄຟລ໌:
```bash
cd /var/www/html/pospharma
chmod 755 -R .
chmod 777 -R assets/images/
chmod 777 -R assets/media/
chmod 777 -R logs/
chown -R www-data:www-data .
```

> **cPanel**: ບໍ່ຕ້ອງ chmod ເພາະ File Manager ຈັດການໃຫ້. ແຕ່ກວດວ່າ `assets/images/` ແລະ `logs/` ເຂົ້າເຖິງໄດ້.

### ຂັ້ນຕອນທີ 2.3: ສ້າງ ແລະ Import ຖານຂໍ້ມູນ (Database Setup)
1. ເຂົ້າໄປທີ່ **phpMyAdmin** (ຫຼື Database Tool ທີ່ທ່ານໃຊ້)
2. ສ້າງ Database ໃໝ່ຊື່: `pospharma`
3. ຕັ້ງຄ່າ Collation ເປັນ: `utf8mb4_unicode_ci` (ເພື່ອຮອງຮັບພາສາລາວ ແລະ ພາສາອື່ນໆຢ່າງຖືກຕ້ອງ)
4. ກົດທີ່ tab **Import** ແລ້ວເລືອກໄຟລ໌ SQL ຈາກ: `database/pospharma.sql`
5. ກົດ **Go** ຫຼື **Import** ເພື່ອສ້າງຕາຕະລາງ ແລະ ຂໍ້ມູນເລີ່ມຕົ້ນ.

### ຂັ້ນຕອນທີ 2.4: ຕັ້ງຄ່າການເຊື່ອມຕໍ່ຖານຂໍ້ມູນ
ແກ້ໄຂໄຟລ໌ [config/database.php](file:///c:/xampp/htdocs/pospharma/config/database.php) ໃຫ້ກົງກັບ Server ຈິງຂອງທ່ານ:

```php
// ຕັ້ງຄ່າ DB Connection
define('DB_HOST', getenv('DB_HOST') ?: 'localhost'); // Server ຖານຂໍ້ມູນ (ປົກກະຕິແມ່ນ localhost)
define('DB_NAME', getenv('DB_NAME') ?: 'pospharma'); // ຊື່ Database
define('DB_USER', getenv('DB_USER') ?: 'root');      // User ຂອງ Database
define('DB_PASS', getenv('DB_PASS') ?: '12345678');  // ລະຫັດຜ່ານ Database

// ຕັ້ງຄ່າ URL ຂອງເວັບໄຊ (ສຳຄັນຫຼາຍ)
// ຖ້າໃຊ້ root domain ເຊັ່ນ https://mypharmacy.com/ ໃຫ້ຕັ້ງເປັນ '/'
// ຖ້າໃຊ້ subfolder ເຊັ່ນ http://localhost/pospharma/ ໃຫ້ຕັ້ງເປັນ '/pospharma/'
define('BASE_URL', getenv('BASE_URL') ?: '/pospharma/');
```

*ໝາຍເຫດ: ທ່ານສາມາດສ້າງໄຟລ໌ `.env` ໄວ້ທີ່ root directory ຂອງໂຄງການເພື່ອເກັບຄ່າ Config ເຫຼົ່ານີ້ໄດ້ໂດຍບໍ່ຕ້ອງແກ້ໄຂໄຟລ໌ PHP ໂດຍກົງ (ເບິ່ງຕົວຢ່າງໃນ [.env.example](file:///c:/xampp/htdocs/pospharma/.env.example)).*

### ຂັ້ນຕອນທີ 2.5: ເປີດໃຊ້ງານ SSL / HTTPS (ແນະນຳ)
ເມື່ອຕິດຕັ້ງ SSL Certificate ທີ່ Server ຮຽບຮ້ອຍແລ້ວ, ໃຫ້ເປີດ force HTTPS ໂດຍການເປີດໄຟລ໌ [.htaccess](file:///c:/xampp/htdocs/pospharma/.htaccess) ຢູ່ root directory ແລ້ວເອົາເຄື່ອງໝາຍ `#` ອອກຢູ່ 3 ແຖວນີ້:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 3. ຂໍ້ມູນເຂົ້າສູ່ລະບົບເລີ່ມຕົ້ນ (Default Credentials)
ຫຼັງຈາກຕິດຕັ້ງສຳເລັດ, ໃຫ້ເຂົ້າໄປທີ່ URL ຂອງລະບົບ ແລະ ເຂົ້າສູ່ລະບົບດ້ວຍ Account ດັ່ງນີ້:
* **URL**: `http://localhost/pospharma/login.php` (ຫຼື ຕາມ Domain ຂອງທ່ານ)
* **ຊື່ຜູ້ໃຊ້ (Username)**: `admin`
* **ລະຫັດຜ່ານ (Password)**: `password` (ລະຫັດເລີ່ມຕົ້ນຈາກ sql seed ຫຼັກ)

> ⚠️ **ຄຳເຕືອນ**: ໃຫ້ເຂົ້າໄປທີ່ເມນູ **"ພະນັກງານ"** ແລະ ທຳການປ່ຽນແປງລະຫັດຜ່ານຂອງ admin ທັນທີ ຫຼັງຈາກເຂົ້າສູ່ລະບົບຄັ້ງທຳອິດ ເພື່ອຄວາມປອດໄພ.

---

# 🇱🇦 ພາກທີ 2: ຄູ່ມືການນຳໃຊ້ລະບົບ (User Manual)

ລະບົບຂາຍຢາດີ ຖືກອອກແບບມາໃຫ້ໃຊ້ງານງ່າຍ, ວ່ອງໄວ, ແລະ ຮອງຮັບການສະແກນ Barcode.

---

## 1. ລະບົບຂາຍຢາ (POS - Point of Sale)
ເມນູ **"ໜ້າຂາຍ"** ແມ່ນເມນູຫຼັກສຳລັບເພດສະຊາກອນ ຫຼື ພະນັກງານຂາຍ:
1. **การຄົ້ນຫາຢາ**: 
   * ສາມາດໃຊ້ **ເຄື່ອງສະແກນບາໂຄດ** ສະແກນໃສ່ກ່ອງຢາໄດ້ທັນທີ ລະບົບຈະເພີ່ມຢາເຂົ້າກະຕ່າອັດຕະໂນມັດ.
   * ຫຼື ພິມຄົ້ນຫາຊື່ຢາຢູ່ຊ່ອງ "ຄົ້ນຫາ ຫຼື ສະແກນບາໂຄດ".
   * ຫຼື ກົດເລືອກຕາມ **ໝວດໝູ່ຢາ** ດ້ານເທິງ.
2. **ການຈັດການກະຕ່າສິນຄ້າ**:
   * ສາມາດປັບປ່ຽນຈຳນວນຢາ ໂດຍການພິມຕົວເລກ ຫຼື ກົດປຸ່ມ `+` / `-`.
   * ສາມາດກວດສອບລາຄາ, ສ່ວນຫຼຸດ, ແລະ ພາສີ ທີ່ຄິດໄລ່ອັດຕະໂນມັດ.
3. **ການຊຳລະເງິນ**:
   * ຮອງຮັບ **ເງິນສົດ (Cash)**: ພິມຈຳນວນເງິນທີ່ຮັບມາ, ລະບົບຈະຄິດໄລ່ເງິນທອນໃຫ້.
   * ຮອງຮັບ **ໂອນເງິນ (Transfer)**: ລະບົບຈະສະແດງ QR Code ບັນຊີທະນາຄານຂອງຮ້ານ (ຕັ້ງຄ່າໄດ້ຢູ່ເມນູຕັ້ງຄ່າ).
   * ຮອງຮັບ **ແບ່ງຈ່າຍ (Split)**: ຈ່າຍສົດບາງສ່ວນ ແລະ ໂອນບາງສ່ວນ.
4. **ການພິມບິນ**:
   * ຫຼັງຈາກກົດ "ບັນທຶກການຂາຍ", ລະບົບຈະສະແດງປ໋ອບອັບໃຫ້ພິມບິນຮັບເງິນ (ຂະໜາດ 80mm ຫຼື ຂະໜາດອື່ນໆ ຕາມທີ່ຕັ້ງຄ່າໄວ້).
   * ສາມາດພິມ **ສະຕິກເກີຕິດຊອງຢາ (Medicine Label)** ທີ່ບອກວິທີການກິນຢາໃຫ້ລູກຄ້າໄດ້ຢ່າງສະດວກ.

---

## 2. การຈັດການສາງ ແລະ ສິນຄ້າ (Inventory & Products)
* **ໝວດໝູ່ຢາ**: ຕັ້ງຄ່າໝວດໝູ່ຢາ (ເຊັ່ນ: ຢາແກ້ປວດ, ຢາຕ້ານເຊື້ອ, ວິຕາມິນ) ເພື່ອໃຫ້ງ່າຍໃນການຈັດໝວດ ແລະ ຄົ້ນຫາ.
* **ເພີ່ມ/ແກ້ໄຂຢາ**: ໃສ່ຂໍ້ມູນຊື່ຢາ, ບາໂຄດ, ລາຄາຊື້ (ຕົ້ນທຶນ), ລາຄາຂາຍ, ຈຳນວນໃນສາງ, ຈຸດເຕືອນສະຕ໋ອກຕໍ່າ (Min Stock), ແລະ ວັນໝົດອາຍຸ.
* **ລະບົບ FIFO (First-In, First-Out)**: ລະບົບຮອງຮັບການຈັດການສະຕ໋ອກແບບ FIFO ໂດຍຈະຕັດຢາລ໋ອດທີ່ເກົ່າ/ໝົດອາຍຸກ່ອນອອກຈາກສາງກ່ອນ ເມື່ອມີການຂາຍ.
* **ການແຈ້ງເຕືອນ**: ລະບົບຈະແຈ້ງເຕືອນຢູ່ Dashboard ທັນທີເມື່ອ:
   * ຢາໃກ້ຈະໝົດສະຕ໋ອກ (Stock Low).
   * ຢາໃກ້ໝົດອາຍຸ (Expiring Soon) ຫຼື ໝົດອາຍຸແລ້ວ (Expired).

---

## 3. ການຈັດການໃບສັ່ງຊື້ ແລະ ຜູ້ສະໜອງ (Purchase Orders & Suppliers)
* **ຜູ້ສະໜອງ (Suppliers)**: ບັນທຶກຂໍ້ມູນບໍລິສັດຢາ ຫຼື ຕົວແທນຈຳໜ່າຍຢາ.
* **ໃບສັ່ງຊື້ (Purchase Orders)**: ສ້າງໃບສັ່ງຊື້ຢາເຂົ້າຮ້ານ ເພື່ອຕິດຕາມການນຳເຂົ້າຢາ ແລະ ຕົ້ນທຶນ.
* **ການນຳເຂົ້າສະຕ໋ອກ (Stock In)**: ເມື່ອໄດ້ຮັບຢາແລ້ວ, ສາມາດກົດຮັບສິນຄ້າເຂົ້າສາງ ລະບົບຈະເພີ່ມຈຳນວນຢາໃນສາງ ແລະ ບັນທຶກປະຫວັດການນຳເຂົ້າ (ລວມທັງເລກ Batch No. ແລະ ວັນໝົດອາຍຸຂອງລ໋ອດນັ້ນໆ) ໃຫ້ອັດຕະໂນມັດ.

---

## 4. ບົດລາຍງານ ແລະ ການເງິນ (Reports & Finance)
* **ລາຍງານການຂາຍ (Dashboard)**: ສະແດງກຣາຟຍອດຂາຍປະຈຳວັນ, ປະຈຳເດືອນ, ຢາທີ່ຂາຍດີທີ່ສຸດ 5 ອັນດັບ, ແລະ ສະຖິຕິການຂາຍແບບລະອຽດ.
* **ລາຍຮັບ-ລາຍຈ່າຍ (Income & Expenses)**: ບັນທຶກຄ່າໃຊ້ຈ່າຍອື່ນໆພາຍໃນຮ້ານ (ເຊັ່ນ: ຄ່າເຊົ່າຮ້ານ, ຄ່ານ້ຳ, ຄ່າໄຟ, ເງິນເດືອນພະນັກງານ). ລະບົບຈະສະຫຼຸບກຳໄລ-ຂາດທຶນສຸດທິໃຫ້ທ່ານເຫັນຢ່າງຊັດເຈນ.

---

## 5. ການຕັ້ງຄ່າລະບົບ (Shop Settings)
* **ຂໍ້ມູນຮ້ານ (Shop Settings)**: ປ່ຽນຊື່ຮ້ານ, ທີ່ຢູ່, ເບີໂທ, ອັດຕາພາສີ (Tax), ແລະ ຫົວໜ່ວຍເງິນຕາ (ກີບ/ບາດ/$).
* **ໂລໂກ້ ແລະ QR Code**: ອັບໂຫຼດ ໂລໂກ້ຮ້ານ ແລະ QR Code ບັນຊີທະນາຄານ ເພື່ອໃຫ້ສະແດງໃນບິນຮັບເງິນ ແລະ ໜ້າຈໍສະແດງຜົນຊຳລະເງິນ.
* **ລະບົບສຳຮອງຂໍ້ມູນ (Backup & Restore)**: ຜູ້ດູແລລະບົບສາມາດກົດ **"ສຳຮອງຂໍ້ມູນ (Backup)"** ເພື່ອດາວໂຫຼດໄຟລ໌ຖານຂໍ້ມູນ `.sql` ເກັບໄວ້ ແລະ ສາມາດກົດ **"ກູ້ຄືນຂໍ້ມູນ (Restore)"** ຫາກເກີດບັນຫາລະບົບຂັດຂ້ອງ.

---

# 🇬🇧 PART 3: English Summary (Quick Reference)

## 1. Quick Install
1. Copy all project files to your web root (`/var/www/html/pospharma` or `htdocs/pospharma`).
2. Create a MySQL database with `utf8mb4_unicode_ci` collation and import `database/pospharma.sql`.
3. Configure database connection credentials in `config/database.php`.
4. Ensure directories `assets/images/`, `assets/media/`, and `logs/` are writable (`chmod 777`).
5. Open browser, go to URL: `http://yourdomain.com/pospharma/login.php`.
6. Log in with **Username**: `admin` | **Password**: `password`. Change password immediately.

## 2. Core Functional Modules
* **POS Terminal**: Supports barcode scanning, custom discounts, Lao tax rates, and multiple payment methods (Cash, Bank Transfer, Split).
* **Inventory Control**: Real-time stock alerts (low stock & expiry warnings) with strict FIFO (First-In, First-Out) stock depletion.
* **Purchasing (PO)**: Record supplier contacts, generate POs, and receive stock batches with expiry dates.
* **Reports & Financials**: Visual charts showing sales trends, top products, expenses, and net profit/loss.
* **Database Backup**: Simple one-click backup and restore tool for system administrators.
