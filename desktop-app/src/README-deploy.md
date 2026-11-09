# 🚀 ຄູ່ມືການຕິດຕັ້ງ ແລະ ຕັ້ງຄ່າເວັບໄຊ (Web App Deployment Guide)

ຄູ່ມືນີ້ອະທິບາຍວິທີການນຳເອົາລະບົບ **POS Pharmacy (ລະບົບຂາຍຢາດີ)** ໄປ Deploy ໃຊ້ງານໃນຮູບແບບ Web Application. ທ່ານສາມາດເລືອກວິທີການຕິດຕັ້ງໄດ້ 2 ຮູບແບບ ຕາມຄວາມເໝາະສົມ.

---

## ວິທີທີ 1: ຕິດຕັ້ງຜ່ານ Docker (ແນະນຳ - ງ່າຍ ແລະ ໄວທີ່ສຸດ)

ວິທີນີ້ເໝາະສົມກັບການ Deploy ບົນ Cloud VPS (Ubuntu/Debian) ຫຼື ເຄື່ອງພັດທະນາລະບົບທີ່ມີ Docker ຕິດຕັ້ງຢູ່ແລ້ວ.

### ສິ່ງທີ່ຕ້ອງມີ (Prerequisites):
- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose](https://docs.docker.com/compose/install/)

### ຂັ້ນຕອນການຕິດຕັ້ງ:
1. ແຕກໄຟລ໌ Zip ແພັກເກັດ ຫຼື Copy ໂຟນເດີໂຄງການໄປໄວ້ໃນ Server.
2. ເປີດ Terminal ຫຼື Command Prompt ແລ້ວເຂົ້າໄປທີ່ໂຟນເດີໂຄງການ.
3. ຣັນຄຳສັ່ງເພື່ອສ້າງ Container ແລະ ເລີ່ມການເຮັດວຽກ:
   ```bash
   docker compose up -d --build
   ```
4. ລະບົບຈະທຳການ:
   * ສ້າງ Docker Image ຂອງ PHP 8.2 + Apache.
   * ສ້າງ Container ຂອງ Database (MariaDB).
   * ທຳການ Import ຖານຂໍ້ມູນເລີ່ມຕົ້ນຈາກ `database/pospharma.sql` ໃຫ້ອັດຕະໂນມັດ.
5. ເມື່ອສຳເລັດແລ້ວ ທ່ານສາມາດເຂົ້າໃຊ້ງານລະບົບໄດ້ທີ່:
   * **URL**: `http://localhost:8080` (ຫຼື `http://your-server-ip:8080`)
   * **Username**: `admin`
   * **Password**: `password`

---

## ວິທີທີ 2: ຕິດຕັ້ງແບບທຳມະດາ (Manual Deployment)

ວິທີນີ້ເໝາະສົມກັບການຕິດຕັ້ງໃນ Web Hosting ທົ່ວໄປ (cPanel, DirectAdmin) ຫຼື VPS ທີ່ໃຊ້ LAMP Stack.

### 1. ກຽມຖານຂໍ້ມູນ (Database):
1. ເຂົ້າໄປທີ່ **phpMyAdmin** ຫຼື Database Management Tool ຂອງທ່ານ.
2. ສ້າງຖານຂໍ້ມູນໃໝ່ຊື່: `pospharma` (ເລືອກ Collation ເປັນ `utf8mb4_unicode_ci`).
3. ທຳການ Import ໄຟລ໌ຖານຂໍ້ມູນຈາກ: `database/pospharma.sql`.

### 2. ອັບໂຫຼດໄຟລ໌ລະບົບ:
1. ອັບໂຫຼດໄຟລ໌ທັງໝົດໃນແພັກເກັດເວັບໄຊໄປໄວ້ທີ່ Folder ຂອງ Web Server (ເຊັ່ນ `public_html/` ຫຼື folder root ຂອງ domain).

### 3. ຕັ້ງຄ່າການເຊື່ອມຕໍ່:
ທ່ານສາມາດຕັ້ງຄ່າການເຊື່ອມຕໍ່ຖານຂໍ້ມູນໄດ້ 2 ວິທີ:

#### ວິທີ A: ສ້າງໄຟລ໌ `.env` (ແນະນຳ)
ສ້າງໄຟລ໌ `.env` ໄວ້ທີ່ root directory ຂອງລະບົບ ແລ້ວກຳນົດຄ່າດັ່ງນີ້:
```env
APP_ENV=production
DB_HOST=localhost
DB_PORT=3306
DB_NAME=pospharma
DB_USER=ຊື່ຜູ້ໃຊ້ຖານຂໍ້ມູນ
DB_PASS=ລະຫັດຜ່ານຖານຂໍ້ມູນ
BASE_URL=/
```

#### ວິທີ B: ແກ້ໄຂໄຟລ໌ `config/database.php` ໂດຍກົງ
ແກ້ໄຂຄ່າ default ໃນໄຟລ໌ `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'pospharma');
define('DB_USER', 'ຊື່ຜູ້ໃຊ້ຖານຂໍ້ມູນ');
define('DB_PASS', 'ລະຫັດຜ່ານຖານຂໍ້ມູນ');
define('BASE_URL', '/'); // ຖ້າຕິດຕັ້ງໃນ subfolder ໃຫ້ກຳນົດເຊັ່ນ '/myfolder/'
```

### 4. ຕັ້ງຄ່າ Permission ຂອງ Folder (ສະເພາະ Linux):
ຣັນຄຳສັ່ງເຫຼົ່ານີ້ເພື່ອໃຫ້ລະບົບສາມາດບັນທຶກຮູບພາບ ແລະ ໄຟລ໌ log ໄດ້:
```bash
chmod 777 -R assets/images/
chmod 777 -R logs/
```

### 5. ເປີດໃຊ້ງານ HTTPS (ແນະນຳ):
ຫາກຕ້ອງການບັງຄັບໃຊ້ HTTPS ໃຫ້ເປີດໄຟລ໌ `.htaccess` ແລ້ວເອົາເຄື່ອງໝາຍ `#` ອອກໃນແຖວ:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```
