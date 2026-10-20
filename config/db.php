<?php
/**
 * Laundrables Database Configuration & Session Bootstrap
 * Supports MySQL/MariaDB (XAMPP/WAMP/Laragon) with automatic SQLite fallback
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// The shop is in the Philippines, so every date and time uses Manila time
date_default_timezone_set('Asia/Manila');

// Optional per-machine settings (hosting credentials). Not committed to git.
if (file_exists(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}
defined('DB_HOST') || define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
defined('DB_PORT') || define('DB_PORT', getenv('DB_PORT') ?: '3306');
defined('DB_NAME') || define('DB_NAME', getenv('DB_NAME') ?: 'laundrables_db');
defined('DB_USER') || define('DB_USER', getenv('DB_USER') ?: 'root');
defined('DB_PASS') || define('DB_PASS', getenv('DB_PASS') ?: '');

/**
 * Returns a connected PDO instance (MySQL primary, SQLite automatic fallback)
 */
function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        try {
            // Connect straight to the database (works on shared hosting)
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (Throwable $e) {
            // Local dev: create the database if it does not exist yet
            $dsnNoDb = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
            $tempPdo = new PDO($dsnNoDb, DB_USER, DB_PASS, $options);
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        }
        ensure_schema_and_seed($pdo, 'mysql');
        return $pdo;
    } catch (Throwable $mysqlErr) {
        // Automatic fallback to SQLite so the app works even when MySQL is stopped
        $sqlitePath = __DIR__ . '/laundrables.sqlite';
        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        ensure_schema_and_seed($pdo, 'sqlite');
        return $pdo;
    }
}

/**
 * Ensures all tables exist and seeds initial sample records if empty
 */
function ensure_schema_and_seed(PDO $pdo, string $driver): void {
    if ($driver === 'mysql') {
        $sqlFile = dirname(__DIR__) . '/database.sql';
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() === 0 && file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $pdo->exec($sql);
        }
        return;
    }

    // SQLite schema initialization
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_code TEXT NOT NULL UNIQUE,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            phone TEXT NOT NULL DEFAULT '(+63)',
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'customer',
            address TEXT DEFAULT '',
            city TEXT DEFAULT '',
            zip_code TEXT DEFAULT '',
            avatar_data TEXT DEFAULT NULL,
            pref_fragrance_free INTEGER NOT NULL DEFAULT 1,
            pref_low_heat INTEGER NOT NULL DEFAULT 1,
            pref_sms_alerts INTEGER NOT NULL DEFAULT 1,
            pref_email_updates INTEGER NOT NULL DEFAULT 1,
            pref_offers INTEGER NOT NULL DEFAULT 0,
            member_since TEXT NOT NULL DEFAULT 'October 2026',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number TEXT NOT NULL UNIQUE,
            user_id INTEGER DEFAULT NULL,
            customer_name TEXT NOT NULL,
            service_name TEXT NOT NULL DEFAULT 'Wash & Fold',
            unit_type TEXT NOT NULL DEFAULT 'kg',
            unit_price REAL NOT NULL DEFAULT 100.00,
            quantity REAL NOT NULL DEFAULT 3.00,
            total_amount REAL NOT NULL DEFAULT 300.00,
            care_instructions TEXT DEFAULT NULL,
            fragrance_free INTEGER NOT NULL DEFAULT 1,
            low_heat INTEGER NOT NULL DEFAULT 1,
            dropoff_date TEXT NOT NULL,
            dropoff_time TEXT NOT NULL,
            pickup_estimate TEXT NOT NULL DEFAULT 'Oct 9 after 3 PM',
            laundry_status TEXT NOT NULL DEFAULT 'Waiting',
            payment_status TEXT NOT NULL DEFAULT 'Unpaid',
            completed_date TEXT DEFAULT NULL,
            is_today_queue INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS support_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_number TEXT NOT NULL UNIQUE,
            user_id INTEGER NOT NULL,
            request_type TEXT NOT NULL DEFAULT 'help',
            subject TEXT NOT NULL,
            order_number TEXT DEFAULT '',
            message TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'In review',
            latest_update TEXT NOT NULL DEFAULT 'Your team is reviewing your question.',
            created_date TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS support_replies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            support_request_id INTEGER NOT NULL,
            sender_role TEXT NOT NULL DEFAULT 'customer',
            sender_name TEXT NOT NULL,
            reply_text TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount > 0) {
        return;
    }

    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $custPass  = password_hash('password123', PASSWORD_DEFAULT);

    $insertUser = $pdo->prepare("
        INSERT INTO users (
            id, customer_code, first_name, last_name, email, phone, password_hash, role,
            address, city, zip_code, pref_fragrance_free, pref_low_heat, pref_sms_alerts,
            pref_email_updates, pref_offers, member_since
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertUser->execute([1, 'ADM-0001', 'Amir', 'Wagas', 'admin@laundrables.com', '(+63) 917 555 0100', $adminPass, 'admin', '128 Maple Street', 'Calamba', '4027', 1, 1, 1, 1, 0, 'January 2026']);
    $insertUser->execute([2, 'CUS-0018', 'Troy', 'Pogi', 'troypogi@example.com', '(+63) 917 555 0148', $custPass, 'customer', 'Olivarez Homes', 'Calamba', '4027', 1, 1, 1, 1, 0, 'June 2026']);
    $insertUser->execute([3, 'CUS-0019', 'David', 'Pogi', 'davidpogi@example.com', '(+63) 918 555 0199', $custPass, 'customer', 'Maple Street', 'Calamba', '4027', 1, 1, 1, 1, 1, 'July 2026']);

    $seedOrders = [
        ['#LD-1048', 2, 'Troy Pogi', 'Wash & Fold', 'kg', 100.00, 8.5, 850.00, 'Please use fragrance-free detergent and low-heat drying. Separate the white cotton shirts.', 1, 1, 'October 9, 2026', '2:00 PM–4:00 PM', 'Oct 9 after 3 PM', 'In progress', 'Unpaid', null, 1],
        ['#LD-1047', 2, 'Raizen Angeles', 'Wash & Fold', 'kg', 60.00, 6.0, 360.00, 'Standard wash and fold.', 1, 1, 'October 9, 2026', '10:00 AM–12:00 PM', 'Oct 9 after 5 PM', 'Ready for pickup', 'Paid', null, 1],
        ['#LD-1046', null, 'Lebron James', 'Wash & Fold', 'kg', 60.00, 10.0, 600.00, 'Separate jerseys and warm-up gear.', 1, 1, 'October 9, 2026', '10:00 AM–12:00 PM', 'Oct 10 after 10 AM', 'In progress', 'Paid', null, 1],
        ['#LD-1045', null, 'Lolo Bron', 'Dry Clean', 'pcs', 500.00, 2.0, 1000.00, '2 formal coats, gentle dry clean.', 0, 1, 'October 9, 2026', '2:00 PM–4:00 PM', 'Oct 11 after 2 PM', 'Waiting', 'Unpaid', null, 1],
        ['#LD-1044', null, 'Drake Flacko', 'Special Care', 'kg', 450.00, 4.0, 1800.00, 'Delicate wool sweaters and silk blends.', 1, 1, 'October 9, 2026', '10:00 AM–12:00 PM', 'Oct 10 after 4 PM', 'Ready for pickup', 'Paid', null, 1],
        ['#LD-1043', null, 'Olivia Dean', 'Wash & Fold', 'kg', 34.29, 7.0, 240.00, 'Fold neatly in reusable basket.', 1, 1, 'October 9, 2026', '4:00 PM–6:00 PM', 'Oct 10 after 4 PM', 'In progress', 'Paid', null, 1],
        ['#LD-1042', null, 'Liam Neason', 'Dry Clean', 'pcs', 500.00, 9.0, 4500.00, '9 dress shirts and suits, press collars.', 0, 0, 'October 9, 2026', '2:00 PM–4:00 PM', 'Oct 11 after 2 PM', 'Ready for pickup', 'Unpaid', null, 1],
        ['#LD-1041', null, 'Ava Sarap', 'Wash & Fold', 'kg', 60.00, 5.0, 300.00, 'Fragrance-free detergent.', 1, 1, 'October 8, 2026', '2:00 PM–4:00 PM', 'Oct 9 after 2 PM', 'Completed', 'Paid', 'Oct 9, 2026', 1],
        ['#LD-1032', 2, 'Troy Pogi', 'Wash & Fold', 'kg', 100.00, 7.0, 700.00, 'Fragrance-free and low-heat drying.', 1, 1, 'September 29, 2026', '2:00 PM–4:00 PM', 'Sep 30 after 3 PM', 'Completed', 'Paid', 'Sep 30, 2026', 0],
        ['#LD-1019', 2, 'Troy Pogi', 'Dry Clean', 'shirts', 200.00, 2.0, 400.00, 'Light starch on dress shirts.', 0, 0, 'September 22, 2026', '10:00 AM–12:00 PM', 'Sep 23 after 4 PM', 'Completed', 'Paid', 'Sep 23, 2026', 0],
        ['#LD-1008', 2, 'Troy Pogi', 'Wash & Fold', 'kg', 100.00, 8.0, 800.00, 'Standard care preferences.', 1, 1, 'September 15, 2026', '2:00 PM–4:00 PM', 'Sep 16 after 3 PM', 'Completed', 'Paid', 'Sep 16, 2026', 0],
    ];

    $insertOrder = $pdo->prepare("
        INSERT INTO orders (
            order_number, user_id, customer_name, service_name, unit_type, unit_price,
            quantity, total_amount, care_instructions, fragrance_free, low_heat,
            dropoff_date, dropoff_time, pickup_estimate, laundry_status, payment_status,
            completed_date, is_today_queue
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($seedOrders as $row) {
        $insertOrder->execute($row);
    }

    $insertTicket = $pdo->prepare("
        INSERT INTO support_requests (
            id, ticket_number, user_id, request_type, subject, order_number,
            message, status, latest_update, created_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insertTicket->execute([1, 'SR-0024', 2, 'help', 'Confirm my care instructions', '#LD-1048', 'Hi team, please make sure my white cotton shirts are separated and washed with fragrance-free detergent.', 'In review', 'Your team is reviewing your question.', 'Oct 8, 2026']);
    $insertTicket->execute([2, 'SR-0021', 2, 'issue', 'Question about my receipt', '#LD-1032', 'I would like to request an official copy of my receipt from last week.', 'Awaiting your reply', 'Please share a photo of your receipt.', 'Oct 1, 2026']);
    $insertTicket->execute([3, 'SR-0016', 2, 'help', 'Dry cleaning care question', '#LD-1019', 'Do you handle linen blazers under Dry Clean or Special Care?', 'Resolved', 'Your care question has been answered.', 'Sep 24, 2026']);

    $insertReply = $pdo->prepare("
        INSERT INTO support_replies (support_request_id, sender_role, sender_name, reply_text)
        VALUES (?, ?, ?, ?)
    ");
    $insertReply->execute([1, 'customer', 'Troy Pogi', 'Hi team, please make sure my white cotton shirts are separated and washed with fragrance-free detergent.']);
    $insertReply->execute([1, 'admin', 'Amir Wagas (Admin)', 'Noted Troy! We tagged #LD-1048 for white shirt separation and fragrance-free wash.']);
    $insertReply->execute([2, 'customer', 'Troy Pogi', 'I would like to request an official copy of my receipt from last week.']);
    $insertReply->execute([2, 'admin', 'Amir Wagas (Admin)', 'Please share a photo of your claim stub or confirm the exact billing name so we can reissue it right away.']);
    $insertReply->execute([3, 'customer', 'Troy Pogi', 'Do you handle linen blazers under Dry Clean or Special Care?']);
    $insertReply->execute([3, 'admin', 'Amir Wagas (Admin)', 'We recommend Dry Clean for structured linen blazers so they keep their lapel shape!']);
}

/**
 * Returns the currently logged-in user (or defaults to customer #2 Troy Pogi if not explicitly logged out)
 */
function get_current_user_record(PDO $pdo, string $expectedRole = 'customer'): array {
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            return $user;
        }
    }

    if (!empty($_SESSION['logged_out'])) {
        header('Location: login.php');
        exit;
    }

    // Default session bootstrap so pages work immediately when opened
    $defaultId = ($expectedRole === 'admin') ? 1 : 2;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$defaultId]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        return $user;
    }

    header('Location: login.php');
    exit;
}

/**
 * Shop address, taken from the admin/staff profile so it always matches
 * what is saved on the Profile page. Returns ['line' => '...', 'street' => '...'].
 */
function get_shop_address(PDO $pdo): array {
    $row = $pdo->query("SELECT address, city, zip_code FROM users WHERE role IN ('admin', 'staff') ORDER BY id ASC LIMIT 1")->fetch();
    $street = trim((string) ($row['address'] ?? ''));
    // "Street, City ZIP"
    $line = $street;
    if (!empty($row['city'])) {
        $line .= ($line !== '' ? ', ' : '') . trim($row['city']);
    }
    if (!empty($row['zip_code'])) {
        $line .= ($line !== '' ? ' ' : '') . trim($row['zip_code']);
    }
    return ['line' => $line !== '' ? $line : 'our shop', 'street' => $street];
}

/**
 * Formats a number as Philippine Peso (₱1,234.56)
 */
function format_php(float $amount): string {
    return '₱' . number_format($amount, 2);
}

/**
 * Formats quantity and unit (e.g., "8.5 kg" or "2 pcs")
 */
function format_quantity_unit(float $qty, string $unit): string {
    if ($unit === 'kg') {
        return number_format($qty, 1) . ' kg';
    }
    $intQty = (int) round($qty);
    if ($unit === 'item' || $unit === 'pcs') {
        return $intQty . ($intQty === 1 ? ' pc' : ' pcs');
    }
    return $intQty . ' ' . $unit;
}

/**
 * Returns user initials (e.g., "TP")
 */
function get_user_initials(string $firstName, string $lastName): string {
    $f = mb_substr(trim($firstName), 0, 1);
    $l = mb_substr(trim($lastName), 0, 1);
    return mb_strtoupper($f . $l) ?: 'U';
}

/**
 * Generates next sequential Order Number (#LD-1049, etc.)
 */
function generate_next_order_number(PDO $pdo): string {
    $rows = $pdo->query("SELECT order_number FROM orders")->fetchAll(PDO::FETCH_COLUMN);
    $max = 1048;
    foreach ($rows as $num) {
        if (preg_match('/(\d+)/', $num, $m)) {
            $val = (int) $m[1];
            if ($val > $max) {
                $max = $val;
            }
        }
    }
    return '#LD-' . ($max + 1);
}

/**
 * Generates next sequential Customer Code (CUS-0020, etc.)
 */
function generate_next_customer_code(PDO $pdo): string {
    $rows = $pdo->query("SELECT customer_code FROM users WHERE customer_code LIKE 'CUS-%'")->fetchAll(PDO::FETCH_COLUMN);
    $max = 18;
    foreach ($rows as $code) {
        if (preg_match('/(\d+)/', $code, $m)) {
            $val = (int) $m[1];
            if ($val > $max) {
                $max = $val;
            }
        }
    }
    return sprintf('CUS-%04d', $max + 1);
}

/**
 * Generates next sequential Support Ticket Number (SR-0025, etc.)
 */
function generate_next_ticket_number(PDO $pdo): string {
    $rows = $pdo->query("SELECT ticket_number FROM support_requests")->fetchAll(PDO::FETCH_COLUMN);
    $max = 24;
    foreach ($rows as $code) {
        if (preg_match('/(\d+)/', $code, $m)) {
            $val = (int) $m[1];
            if ($val > $max) {
                $max = $val;
            }
        }
    }
    return sprintf('SR-%04d', $max + 1);
}
