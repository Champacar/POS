<?php
/**
 * Database migration for Pharmacist role and granular permissions
 */
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    echo "Checking users table structure...\n";

    // 1. Modify role ENUM to include 'pharmacist'
    $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','cashier','manager','developer','pharmacist') NOT NULL DEFAULT 'cashier'");
    echo "✓ Role ENUM updated successfully with 'pharmacist'.\n";

    // 2. Check if permissions column exists
    $cols = $db->query("SHOW COLUMNS FROM users LIKE 'permissions'")->fetchAll();
    if (empty($cols)) {
        $db->exec("ALTER TABLE users ADD COLUMN permissions TEXT NULL AFTER role");
        echo "✓ Added 'permissions' column to users table.\n";
    } else {
        echo "- 'permissions' column already exists.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
