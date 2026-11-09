# Project Overview & Learned Guidelines: POS Pharma

## 1. Project & Deployment Architecture
- **Application Stack:** Traditional PHP 8.2 + Apache + MySQL / MariaDB (also supports SQLite fallback for local desktop mode).
- **GitHub Repository:** `https://github.com/Champacar/POS.git` (branch: `main`).
- **Live Deployment Platform:** Railway (`dazzling-balance` project).
- **Live Web URL:** `https://pospharma-web-production.up.railway.app`
- **Default Admin Account:** `admin` / `password`

---

## 2. Docker & Cloud Runtime Guidelines (Railway / Cloud)
- **Apache MPM Conflict Fix:** When using `php:8.2-apache` on Railway, always use `docker-entrypoint.sh` to remove conflicting MPMs before booting:
  ```bash
  rm -f /etc/apache2/mods-enabled/mpm_event.*
  rm -f /etc/apache2/mods-enabled/mpm_worker.*
  a2enmod mpm_prefork > /dev/null 2>&1 || true
  ```
- **Dynamic Port Binding:** Railway binds dynamic `$PORT`. `docker-entrypoint.sh` dynamically updates Apache's `ports.conf` and `000-default.conf` to listen on `${PORT}`.
- **File & Upload Permissions:** `logs/` and `assets/images/` must have write permissions for `www-data` (`chmod -R 777` in Docker).

---

## 3. Database & Auto-Seed Convention
- **Auto-Migration & Auto-Seed:** `config/database.php` automatically detects if the MySQL database is empty (checks if `users` table exists). If missing, it automatically seeds `database/pospharma.sql` using `PDO::MYSQL_ATTR_MULTI_STATEMENTS => true` without requiring manual SQL import via GUI tools.
- **Database Environment Variables:**
  - `DB_HOST`: Hostname (e.g. `${{MySQL.MYSQLHOST}}` or `mysql.railway.internal`).
  - `DB_PORT`: Port (default 3306).
  - `DB_NAME`: Database name (e.g. `railway`).
  - `DB_USER`: Database username.
  - `DB_PASS`: Database password.
  - `DB_SSL`: Set to `true` if connecting to SSL-mandatory cloud providers (TiDB / Aiven).

---

## 4. Git & Build Ignored Paths
- The repository contains a desktop app (`desktop-app/`) and build outputs (`dist/`) exceeding 1.1GB.
- Always ensure `desktop-app/bin/`, `desktop-app/node_modules/`, `dist/`, and `*.exe` are ignored in `.gitignore` and `.dockerignore` to prevent hitting GitHub's 100MB file limit and slowing down Docker builds.
