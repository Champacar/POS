# Walkthrough - Developer Settings Panel & Tools

I have successfully implemented the "Server Settings" configuration panel and Developer Tools page (`server_settings.php`) for the user `developer`.

## Features Added

### 1. Simple Dotenv (.env) Loader
Added a simple `.env` parser at the very top of `config/database.php`. This loads keys directly into `$_ENV`, `$_SERVER`, and `putenv` to seamlessly overwrite config values:
- `APP_ENV` (e.g. `development` or `production`)
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `BASE_URL`
- `PHP_PORT` (for the built-in desktop app port)

### 2. Developer Access Enforcement (`includes/functions.php`)
Added a new `requireDeveloper()` authorization check function to restrict direct page access or API calls exclusively to logged-in users with the `'developer'` role.

### 3. Server Settings Page (`server_settings.php`)
Created a dedicated dashboard with:
- **Environment (.env) Manager Form**: Directly edits and saves environment variables to the project root `.env` file.
- **System Information Table**: Displays details like PHP Version, MySQL connection status, OS, server paths, and active extensions.
- **SQL Console**: A secure terminal area that executes raw queries and displays results inside interactive tables with quick-fill template buttons for common database operations.

### 4. Conditional Sidebar Link (`includes/sidebar.php`)
Inserted a link to `server_settings.php` that is visible **only** to logged-in users with the `'developer'` role.

---

## Verification & Synchronization

- Verified that all modified PHP scripts pass syntax validation checks with no errors.
- Automatically synchronized all code additions across directories:
  - `pospharma/`
  - `pospharma/desktop-app/src/`
  - `pospharma/dist/desktop/win-unpacked/resources/app/src/`

## How to Access

1. Log in as the `developer` user.
2. Click **ຕັ້ງຄ່າເຊີເວີ (Server Settings)** in the left sidebar menu.
3. Use the page to configure environment properties or execute database queries directly.
