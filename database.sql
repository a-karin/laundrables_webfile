-- ============================================================
-- Laundrables Laundry Shop Database Schema & Seed Data
-- Compatible with MySQL 5.7+ / MariaDB 10.x (XAMPP / WAMP / Laragon)
-- ============================================================

-- Import this into an EXISTING database (select it first in phpMyAdmin).

-- ------------------------------------------------------------
-- 1. USERS TABLE (Customers, Staff, and Administrators)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_code` VARCHAR(20) NOT NULL UNIQUE,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(40) NOT NULL DEFAULT '(+63)',
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('customer', 'admin', 'staff') NOT NULL DEFAULT 'customer',
    `address` VARCHAR(255) DEFAULT '',
    `city` VARCHAR(100) DEFAULT '',
    `zip_code` VARCHAR(20) DEFAULT '',
    `avatar_data` LONGTEXT DEFAULT NULL,
    `pref_fragrance_free` TINYINT(1) NOT NULL DEFAULT 1,
    `pref_low_heat` TINYINT(1) NOT NULL DEFAULT 1,
    `pref_sms_alerts` TINYINT(1) NOT NULL DEFAULT 1,
    `pref_email_updates` TINYINT(1) NOT NULL DEFAULT 1,
    `pref_offers` TINYINT(1) NOT NULL DEFAULT 0,
    `member_since` VARCHAR(40) NOT NULL DEFAULT 'October 2026',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. ORDERS TABLE (Laundry Orders & Queue Management)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_number` VARCHAR(20) NOT NULL UNIQUE,
    `user_id` INT DEFAULT NULL,
    `customer_name` VARCHAR(150) NOT NULL,
    `service_name` VARCHAR(60) NOT NULL DEFAULT 'Wash & Fold',
    `unit_type` VARCHAR(20) NOT NULL DEFAULT 'kg',
    `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 100.00,
    `quantity` DECIMAL(10,2) NOT NULL DEFAULT 3.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 300.00,
    `care_instructions` TEXT DEFAULT NULL,
    `fragrance_free` TINYINT(1) NOT NULL DEFAULT 1,
    `low_heat` TINYINT(1) NOT NULL DEFAULT 1,
    `dropoff_date` VARCHAR(50) NOT NULL,
    `dropoff_time` VARCHAR(50) NOT NULL,
    `pickup_estimate` VARCHAR(80) NOT NULL DEFAULT 'Oct 9 after 3 PM',
    `laundry_status` ENUM('Waiting', 'In progress', 'Ready for pickup', 'Completed') NOT NULL DEFAULT 'Waiting',
    `payment_status` ENUM('Unpaid', 'Paid', 'Refunded') NOT NULL DEFAULT 'Unpaid',
    `completed_date` VARCHAR(40) DEFAULT NULL,
    `is_today_queue` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. SUPPORT REQUESTS TABLE (Customer Help & Issue Tickets)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_number` VARCHAR(20) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `request_type` ENUM('issue', 'help') NOT NULL DEFAULT 'help',
    `subject` VARCHAR(200) NOT NULL,
    `order_number` VARCHAR(30) DEFAULT '',
    `message` TEXT NOT NULL,
    `status` ENUM('In review', 'Awaiting your reply', 'Resolved') NOT NULL DEFAULT 'In review',
    `latest_update` VARCHAR(255) NOT NULL DEFAULT 'Your team is reviewing your question.',
    `created_date` VARCHAR(40) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_support_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. SUPPORT REPLIES TABLE (Ticket Conversation History)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_replies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `support_request_id` INT NOT NULL,
    `sender_role` ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    `sender_name` VARCHAR(120) NOT NULL,
    `reply_text` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_replies_request` FOREIGN KEY (`support_request_id`) REFERENCES `support_requests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- SEED DATA
-- Default Passwords:
--   Customer (troypogi@example.com / davidpogi@example.com): password123
--   Admin (admin@laundrables.com): admin123
-- ------------------------------------------------------------

INSERT INTO `users` (
    `id`, `customer_code`, `first_name`, `last_name`, `email`, `phone`,
    `password_hash`, `role`, `address`, `city`, `zip_code`,
    `pref_fragrance_free`, `pref_low_heat`, `pref_sms_alerts`, `pref_email_updates`, `pref_offers`, `member_since`
) VALUES
(1, 'ADM-0001', 'Amir', 'Wagas', 'admin@laundrables.com', '(+63) 917 555 0100',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',
 '128 Maple Street', 'Calamba', '4027', 1, 1, 1, 1, 0, 'January 2026'),
(2, 'CUS-0018', 'Troy', 'Pogi', 'troypogi@example.com', '(+63) 917 555 0148',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer',
 'Olivarez Homes', 'Calamba', '4027', 1, 1, 1, 1, 0, 'June 2026'),
(3, 'CUS-0019', 'David', 'Pogi', 'davidpogi@example.com', '(+63) 918 555 0199',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer',
 'Maple Street', 'Calamba', '4027', 1, 1, 1, 1, 1, 'July 2026')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

INSERT INTO `orders` (
    `order_number`, `user_id`, `customer_name`, `service_name`, `unit_type`,
    `unit_price`, `quantity`, `total_amount`, `care_instructions`,
    `fragrance_free`, `low_heat`, `dropoff_date`, `dropoff_time`,
    `pickup_estimate`, `laundry_status`, `payment_status`, `completed_date`, `is_today_queue`
) VALUES
('#LD-1048', 2, 'Troy Pogi', 'Wash & Fold', 'kg', 100.00, 8.50, 850.00,
 'Please use fragrance-free detergent and low-heat drying. Separate the white cotton shirts.',
 1, 1, 'October 9, 2026', '2:00 PM–4:00 PM', 'Oct 9 after 3 PM', 'In progress', 'Unpaid', NULL, 1),
('#LD-1047', 2, 'Raizen Angeles', 'Wash & Fold', 'kg', 60.00, 6.00, 360.00,
 'Standard wash and fold.', 1, 1, 'October 9, 2026', '10:00 AM–12:00 PM', 'Oct 9 after 5 PM', 'Ready for pickup', 'Paid', NULL, 1),
('#LD-1046', NULL, 'Lebron James', 'Wash & Fold', 'kg', 60.00, 10.00, 600.00,
 'Separate jerseys and warm-up gear.', 1, 1, 'October 9, 2026', '10:00 AM–12:00 PM', 'Oct 10 after 10 AM', 'In progress', 'Paid', NULL, 1),
('#LD-1045', NULL, 'Lolo Bron', 'Dry Clean', 'pcs', 500.00, 2.00, 1000.00,
 '2 formal coats, gentle dry clean.', 0, 1, 'October 9, 2026', '2:00 PM–4:00 PM', 'Oct 11 after 2 PM', 'Waiting', 'Unpaid', NULL, 1),
('#LD-1044', NULL, 'Drake Flacko', 'Special Care', 'kg', 450.00, 4.00, 1800.00,
 'Delicate wool sweaters and silk blends.', 1, 1, 'October 9, 2026', '10:00 AM–12:00 PM', 'Oct 10 after 4 PM', 'Ready for pickup', 'Paid', NULL, 1),
('#LD-1043', NULL, 'Olivia Dean', 'Wash & Fold', 'kg', 34.29, 7.00, 240.00,
 'Fold neatly in reusable basket.', 1, 1, 'October 9, 2026', '4:00 PM–6:00 PM', 'Oct 10 after 4 PM', 'In progress', 'Paid', NULL, 1),
('#LD-1042', NULL, 'Liam Neason', 'Dry Clean', 'pcs', 500.00, 9.00, 4500.00,
 '9 dress shirts and suits, press collars.', 0, 0, 'October 9, 2026', '2:00 PM–4:00 PM', 'Oct 11 after 2 PM', 'Ready for pickup', 'Unpaid', NULL, 1),
('#LD-1041', NULL, 'Ava Sarap', 'Wash & Fold', 'kg', 60.00, 5.00, 300.00,
 'Fragrance-free detergent.', 1, 1, 'October 8, 2026', '2:00 PM–4:00 PM', 'Oct 9 after 2 PM', 'Completed', 'Paid', 'Oct 9, 2026', 1),
('#LD-1032', 2, 'Troy Pogi', 'Wash & Fold', 'kg', 100.00, 7.00, 700.00,
 'Fragrance-free and low-heat drying.', 1, 1, 'September 29, 2026', '2:00 PM–4:00 PM', 'Sep 30 after 3 PM', 'Completed', 'Paid', 'Sep 30, 2026', 0),
('#LD-1019', 2, 'Troy Pogi', 'Dry Clean', 'shirts', 200.00, 2.00, 400.00,
 'Light starch on dress shirts.', 0, 0, 'September 22, 2026', '10:00 AM–12:00 PM', 'Sep 23 after 4 PM', 'Completed', 'Paid', 'Sep 23, 2026', 0),
('#LD-1008', 2, 'Troy Pogi', 'Wash & Fold', 'kg', 100.00, 8.00, 800.00,
 'Standard care preferences.', 1, 1, 'September 15, 2026', '2:00 PM–4:00 PM', 'Sep 16 after 3 PM', 'Completed', 'Paid', 'Sep 16, 2026', 0)
ON DUPLICATE KEY UPDATE `laundry_status` = VALUES(`laundry_status`);

INSERT INTO `support_requests` (
    `id`, `ticket_number`, `user_id`, `request_type`, `subject`, `order_number`,
    `message`, `status`, `latest_update`, `created_date`
) VALUES
(1, 'SR-0024', 2, 'help', 'Confirm my care instructions', '#LD-1048',
 'Hi team, please make sure my white cotton shirts are separated and washed with fragrance-free detergent.',
 'In review', 'Your team is reviewing your question.', 'Oct 8, 2026'),
(2, 'SR-0021', 2, 'issue', 'Question about my receipt', '#LD-1032',
 'I would like to request an official copy of my receipt from last week.',
 'Awaiting your reply', 'Please share a photo of your receipt.', 'Oct 1, 2026'),
(3, 'SR-0016', 2, 'help', 'Dry cleaning care question', '#LD-1019',
 'Do you handle linen blazers under Dry Clean or Special Care?',
 'Resolved', 'Your care question has been answered.', 'Sep 24, 2026')
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

INSERT INTO `support_replies` (`support_request_id`, `sender_role`, `sender_name`, `reply_text`) VALUES
(1, 'customer', 'Troy Pogi', 'Hi team, please make sure my white cotton shirts are separated and washed with fragrance-free detergent.'),
(1, 'admin', 'Amir Wagas (Admin)', 'Noted Troy! We tagged #LD-1048 for white shirt separation and fragrance-free wash.'),
(2, 'customer', 'Troy Pogi', 'I would like to request an official copy of my receipt from last week.'),
(2, 'admin', 'Amir Wagas (Admin)', 'Please share a photo of your claim stub or confirm the exact billing name so we can reissue it right away.'),
(3, 'customer', 'Troy Pogi', 'Do you handle linen blazers under Dry Clean or Special Care?'),
(3, 'admin', 'Amir Wagas (Admin)', 'We recommend Dry Clean for structured linen blazers so they keep their lapel shape!');
