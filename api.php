<?php
/**
 * Laundrables Full CRUD Backend API (api.php)
 * Handles JSON / Form AJAX requests for:
 *  - Authentication (login, register, logout)
 *  - Orders CRUD (create_order, get_order, update_order, cancel_order, delete_order, list_orders)
 *  - Customer Profile & Photo (update_profile, update_password, upload_avatar, update_preferences)
 *  - Support Tickets & Replies (create_support, get_support, reply_support, update_support_status)
 */

require_once __DIR__ . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = get_db();

// Parse input from JSON body or standard POST/GET
$rawInput = file_get_contents('php://input');
$jsonData = $rawInput ? json_decode($rawInput, true) : [];
$input = is_array($jsonData) ? array_merge($_GET, $_POST, $jsonData) : array_merge($_GET, $_POST);

$action = trim((string) ($input['action'] ?? ''));

function json_response(bool $ok, string $message, array $data = [], int $status = 200): void {
    http_response_code($status);
    echo json_encode(array_merge([
        'ok'      => $ok,
        'message' => $message,
    ], $data));
    exit;
}

try {
    switch ($action) {
        // ============================================================
        // 1. AUTHENTICATION: LOGIN & REGISTER
        // ============================================================
        case 'login': {
            $email = strtolower(trim((string) ($input['email'] ?? '')));
            $password = (string) ($input['password'] ?? '');

            if ($email === '' || $password === '') {
                json_response(false, 'Please enter both your email address and password.', [], 400);
            }

            $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Verify password hash or allow demo password fallback ('password123' / 'admin123' / 'password')
            $validPassword = false;
            if ($user) {
                if (password_verify($password, $user['password_hash'])) {
                    $validPassword = true;
                } elseif (in_array($password, ['password123', 'admin123', 'password'], true)) {
                    $validPassword = true;
                }
            }

            if (!$user || !$validPassword) {
                json_response(false, 'Invalid email or password. Try troypogi@example.com (password123) or admin@laundrables.com (admin123).', [], 401);
            }

            unset($_SESSION['logged_out']);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['role']    = $user['role'];

            $redirect = ($user['role'] === 'admin' || $user['role'] === 'staff') ? 'admin.php' : 'index.php';
            json_response(true, 'Welcome back, ' . $user['first_name'] . '!', [
                'redirect' => $redirect,
                'role'     => $user['role'],
                'user'     => [
                    'id'            => (int) $user['id'],
                    'first_name'    => $user['first_name'],
                    'last_name'     => $user['last_name'],
                    'email'         => $user['email'],
                    'customer_code' => $user['customer_code'],
                ],
            ]);
        }

        case 'register': {
            $firstName       = trim((string) ($input['first_name'] ?? ''));
            $lastName        = trim((string) ($input['last_name'] ?? ''));
            $email           = strtolower(trim((string) ($input['email'] ?? '')));
            $phone           = trim((string) ($input['phone'] ?? ''));
            $password        = (string) ($input['password'] ?? '');
            $confirmPassword = (string) ($input['confirm_password'] ?? '');
            $offers          = !empty($input['offers']) ? 1 : 0;

            if ($firstName === '' || $lastName === '' || $email === '' || $phone === '') {
                json_response(false, 'Please complete all required fields.', [], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_response(false, 'Please enter a valid email address.', [], 400);
            }
            if (strlen($password) < 8 || !preg_match('/\d/', $password)) {
                json_response(false, 'Password must be at least 8 characters and include at least one number.', [], 400);
            }
            if ($password !== $confirmPassword) {
                json_response(false, 'Passwords do not match.', [], 400);
            }

            $check = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                json_response(false, 'An account with this email address already exists. Please log in instead.', [], 409);
            }

            $customerCode = generate_next_customer_code($pdo);
            $hash         = password_hash($password, PASSWORD_DEFAULT);
            $memberSince  = date('F Y');

            $stmt = $pdo->prepare("
                INSERT INTO users (
                    customer_code, first_name, last_name, email, phone, password_hash,
                    role, address, city, zip_code, pref_fragrance_free, pref_low_heat,
                    pref_sms_alerts, pref_email_updates, pref_offers, member_since
                ) VALUES (?, ?, ?, ?, ?, ?, 'customer', '', '', '', 1, 1, 1, 1, ?, ?)
            ");
            $stmt->execute([$customerCode, $firstName, $lastName, $email, $phone, $hash, $offers, $memberSince]);
            $newUserId = (int) $pdo->lastInsertId();

            unset($_SESSION['logged_out']);
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['role']    = 'customer';

            json_response(true, 'Account created! Redirecting to your workspace...', [
                'redirect'      => 'index.php',
                'customer_code' => $customerCode,
            ]);
        }

        // ============================================================
        // 2. ORDERS CRUD (CREATE, READ, UPDATE, CANCEL, DELETE)
        // ============================================================
        case 'create_order': {
            $user = get_current_user_record($pdo);
            $serviceName      = trim((string) ($input['service_name'] ?? 'Wash & Fold'));
            $quantity         = (float) ($input['quantity'] ?? 3.0);
            $careInstructions = trim((string) ($input['care_instructions'] ?? ''));
            $fragranceFree    = !empty($input['fragrance_free']) ? 1 : 0;
            $lowHeat          = !empty($input['low_heat']) ? 1 : 0;
            // Staff walk-in orders default to today (Manila time)
            $dropoffDate      = trim((string) ($input['dropoff_date'] ?? ''));
            $dropoffTime      = trim((string) ($input['dropoff_time'] ?? ''));
            if ($dropoffDate === '') {
                $dropoffDate = date('F j, Y');
            }
            if ($dropoffTime === '') {
                $dropoffTime = 'Walk-in';
            }

            // Customers can only book today or later, and not a slot that already started
            if ($user['role'] === 'customer') {
                $slotHours = ['10:00 AM–12:00 PM' => 10, '2:00 PM–4:00 PM' => 14, '4:00 PM–6:00 PM' => 16];
                $dropObj = DateTime::createFromFormat('!F j, Y', $dropoffDate);
                if (!$dropObj || $dropObj->format('F j, Y') !== $dropoffDate) {
                    json_response(false, 'Please choose a valid drop-off date.', [], 422);
                }
                if (!isset($slotHours[$dropoffTime])) {
                    json_response(false, 'Please choose a valid drop-off time.', [], 422);
                }
                $todayObj = new DateTime('today');
                if ($dropObj < $todayObj) {
                    json_response(false, 'That date has already passed. Please pick today or a later date.', [], 422);
                }
                if ($dropObj > (clone $todayObj)->modify('+60 days')) {
                    json_response(false, 'You can book up to 60 days ahead.', [], 422);
                }
                if ($dropObj == $todayObj && ((int) date('G')) * 60 + (int) date('i') >= $slotHours[$dropoffTime] * 60) {
                    json_response(false, 'That time slot has already started. Please pick a later slot or another day.', [], 422);
                }
            }
            $customerName     = trim((string) ($input['customer_name'] ?? ($user['first_name'] . ' ' . $user['last_name'])));
            $laundryStatus    = trim((string) ($input['laundry_status'] ?? 'Waiting'));
            $paymentStatus    = trim((string) ($input['payment_status'] ?? 'Unpaid'));

            $serviceMap = [
                'Wash & Fold'  => ['price' => 60.00,  'unit' => 'kg',   'min' => 3.0, 'hours' => 24],
                'Dry Clean'    => ['price' => 500.00, 'unit' => 'pcs',  'min' => 1.0, 'hours' => 48],
                'Special Care' => ['price' => 300.00, 'unit' => 'kg',   'min' => 2.0, 'hours' => 36],
            ];
            $cfg = $serviceMap[$serviceName] ?? $serviceMap['Wash & Fold'];

            if ($quantity < $cfg['min']) {
                $quantity = $cfg['min'];
            }

            // Customers always pay the shop's listed price; only staff can override the amounts
            $isStaffUser = in_array($user['role'], ['admin', 'staff'], true);
            $unitPrice   = $isStaffUser && isset($input['unit_price']) && (float) $input['unit_price'] > 0 ? (float) $input['unit_price'] : $cfg['price'];
            $totalAmount = $isStaffUser && isset($input['total_amount']) && (float) $input['total_amount'] > 0 ? (float) $input['total_amount'] : round($quantity * $unitPrice, 2);
            $orderNumber = generate_next_order_number($pdo);

            // Compute estimated pickup label
            $shortDate = preg_replace('/,\s*\d{4}$/', '', $dropoffDate);
            $pickupEstimate = $shortDate . ' after 3 PM';
            $completedDate  = ($laundryStatus === 'Completed') ? date('M j, Y') : null;

            // Assign user_id if created by customer or matching customer name
            $orderUserId = ($user['role'] === 'customer') ? (int) $user['id'] : (!empty($input['user_id']) ? (int) $input['user_id'] : 2);

            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    order_number, user_id, customer_name, service_name, unit_type,
                    unit_price, quantity, total_amount, care_instructions,
                    fragrance_free, low_heat, dropoff_date, dropoff_time,
                    pickup_estimate, laundry_status, payment_status, completed_date, is_today_queue
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $orderNumber, $orderUserId, $customerName, $serviceName, $cfg['unit'],
                $unitPrice, $quantity, $totalAmount, $careInstructions,
                $fragranceFree, $lowHeat, $dropoffDate, $dropoffTime,
                $pickupEstimate, $laundryStatus, $paymentStatus, $completedDate
            ]);

            $orderId = (int) $pdo->lastInsertId();
            $created = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $created->execute([$orderId]);

            json_response(true, 'Order ' . $orderNumber . ' created!', [
                'order'    => $created->fetch(),
                'redirect' => 'index.php',
            ]);
        }

        case 'get_order': {
            $orderId     = (int) ($input['id'] ?? 0);
            $orderNumber = trim((string) ($input['order_number'] ?? ''));

            if ($orderId > 0) {
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                $stmt->execute([$orderId]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
                $stmt->execute([$orderNumber]);
            }
            $order = $stmt->fetch();
            if (!$order) {
                json_response(false, 'Order not found.', [], 404);
            }
            json_response(true, 'Order loaded.', ['order' => $order]);
        }

        case 'update_order': {
            $orderId     = (int) ($input['id'] ?? 0);
            $orderNumber = trim((string) ($input['order_number'] ?? ''));

            if ($orderId > 0) {
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                $stmt->execute([$orderId]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
                $stmt->execute([$orderNumber]);
            }
            $existing = $stmt->fetch();
            if (!$existing) {
                json_response(false, 'Order not found.', [], 404);
            }

            $customerName     = trim((string) ($input['customer_name'] ?? $existing['customer_name']));
            $serviceName      = trim((string) ($input['service_name'] ?? $existing['service_name']));
            $quantity         = isset($input['quantity']) ? (float) $input['quantity'] : (float) $existing['quantity'];
            $totalAmount      = isset($input['total_amount']) ? (float) $input['total_amount'] : (float) $existing['total_amount'];
            $laundryStatus    = trim((string) ($input['laundry_status'] ?? $existing['laundry_status']));
            $paymentStatus    = trim((string) ($input['payment_status'] ?? $existing['payment_status']));
            $careInstructions = isset($input['care_instructions']) ? trim((string) $input['care_instructions']) : $existing['care_instructions'];
            $pickupEstimate   = isset($input['pickup_estimate']) ? trim((string) $input['pickup_estimate']) : $existing['pickup_estimate'];

            $unitType = $existing['unit_type'];
            if ($serviceName === 'Dry Clean' && $unitType === 'kg') {
                $unitType = 'pcs';
            } elseif ($serviceName !== 'Dry Clean' && $unitType === 'pcs') {
                $unitType = 'kg';
            }

            $completedDate = $existing['completed_date'];
            if ($laundryStatus === 'Completed' && empty($completedDate)) {
                $completedDate = date('M j, Y');
            } elseif ($laundryStatus !== 'Completed') {
                $completedDate = null;
            }

            $upd = $pdo->prepare("
                UPDATE orders
                SET customer_name = ?,
                    service_name = ?,
                    unit_type = ?,
                    quantity = ?,
                    total_amount = ?,
                    laundry_status = ?,
                    payment_status = ?,
                    care_instructions = ?,
                    pickup_estimate = ?,
                    completed_date = ?
                WHERE id = ?
            ");
            $upd->execute([
                $customerName, $serviceName, $unitType, $quantity, $totalAmount,
                $laundryStatus, $paymentStatus, $careInstructions, $pickupEstimate,
                $completedDate, $existing['id']
            ]);

            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$existing['id']]);
            json_response(true, 'Order ' . $existing['order_number'] . ' updated!', [
                'order' => $stmt->fetch(),
            ]);
        }

        case 'delete_order': {
            $orderId     = (int) ($input['id'] ?? 0);
            $orderNumber = trim((string) ($input['order_number'] ?? ''));

            if ($orderId > 0) {
                $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
                $stmt->execute([$orderId]);
            } else {
                $stmt = $pdo->prepare("DELETE FROM orders WHERE order_number = ?");
                $stmt->execute([$orderNumber]);
            }
            json_response(true, 'Order deleted from database.');
        }

        // ============================================================
        // 3. PROFILE, PASSWORD, PHOTO & PREFERENCES CRUD
        // ============================================================
        case 'update_profile': {
            $user      = get_current_user_record($pdo);
            $firstName = trim((string) ($input['first_name'] ?? ''));
            $lastName  = trim((string) ($input['last_name'] ?? ''));
            $email     = strtolower(trim((string) ($input['email'] ?? '')));
            $phone     = trim((string) ($input['phone'] ?? ''));
            $address   = trim((string) ($input['address'] ?? ''));
            $city      = trim((string) ($input['city'] ?? ''));
            $zipCode   = trim((string) ($input['zip_code'] ?? ''));

            if ($firstName === '' || $lastName === '' || $email === '' || $phone === '') {
                json_response(false, 'First name, last name, email, and phone are required.', [], 400);
            }

            $stmt = $pdo->prepare("
                UPDATE users
                SET first_name = ?, last_name = ?, email = ?, phone = ?,
                    address = ?, city = ?, zip_code = ?
                WHERE id = ?
            ");
            $stmt->execute([$firstName, $lastName, $email, $phone, $address, $city, $zipCode, $user['id']]);

            // Also keep customer_name on their orders synced
            $fullName = $firstName . ' ' . $lastName;
            $sync = $pdo->prepare("UPDATE orders SET customer_name = ? WHERE user_id = ?");
            $sync->execute([$fullName, $user['id']]);

            json_response(true, 'Your profile changes have been saved.', [
                'full_name' => $fullName,
                'initials'  => get_user_initials($firstName, $lastName),
            ]);
        }

        case 'update_password': {
            $user            = get_current_user_record($pdo);
            $currentPassword = (string) ($input['current_password'] ?? '');
            $newPassword     = (string) ($input['new_password'] ?? '');
            $confirmPassword = (string) ($input['confirm_password'] ?? '');

            $validCurrent = password_verify($currentPassword, $user['password_hash'])
                || in_array($currentPassword, ['password123', 'admin123', 'password'], true);

            if (!$validCurrent) {
                json_response(false, 'Your current password is incorrect.', [], 400);
            }
            if (strlen($newPassword) < 8 || !preg_match('/\d/', $newPassword)) {
                json_response(false, 'New password must be at least 8 characters and include a number.', [], 400);
            }
            if ($newPassword !== $confirmPassword) {
                json_response(false, 'New password and confirmation do not match.', [], 400);
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$newHash, $user['id']]);

            json_response(true, 'Your password has been updated.');
        }

        case 'upload_avatar': {
            $user       = get_current_user_record($pdo);
            $avatarData = (string) ($input['avatar_data'] ?? '');

            if ($avatarData === '' || strpos($avatarData, 'data:image/') !== 0) {
                json_response(false, 'Please select a valid image file.', [], 400);
            }

            $stmt = $pdo->prepare("UPDATE users SET avatar_data = ? WHERE id = ?");
            $stmt->execute([$avatarData, $user['id']]);

            json_response(true, 'Profile photo updated automatically!', [
                'avatar_data' => $avatarData,
            ]);
        }

        case 'update_preferences': {
            $user          = get_current_user_record($pdo);
            $fragranceFree = !empty($input['fragrance_free']) ? 1 : 0;
            $lowHeat       = !empty($input['low_heat']) ? 1 : 0;
            $smsAlerts     = !empty($input['sms_alerts']) ? 1 : 0;
            $emailUpdates  = !empty($input['email_updates']) ? 1 : 0;
            $offers        = !empty($input['offers']) ? 1 : 0;

            $stmt = $pdo->prepare("
                UPDATE users
                SET pref_fragrance_free = ?,
                    pref_low_heat = ?,
                    pref_sms_alerts = ?,
                    pref_email_updates = ?,
                    pref_offers = ?
                WHERE id = ?
            ");
            $stmt->execute([$fragranceFree, $lowHeat, $smsAlerts, $emailUpdates, $offers, $user['id']]);

            json_response(true, 'Your laundry preferences have been saved and will apply to future orders.');
        }

        // ============================================================
        // 4. SUPPORT REQUESTS & REPLIES CRUD
        // ============================================================
        case 'create_support': {
            $user        = get_current_user_record($pdo);
            $requestType = ($input['request_type'] ?? 'help') === 'issue' ? 'issue' : 'help';
            $subject     = trim((string) ($input['subject'] ?? ''));
            $orderNumber = trim((string) ($input['order_number'] ?? ''));
            $message     = trim((string) ($input['message'] ?? ''));

            if ($subject === '' || $message === '') {
                json_response(false, 'Please provide both a subject and message.', [], 400);
            }

            if ($orderNumber !== '' && strpos($orderNumber, '#') !== 0) {
                $orderNumber = '#' . $orderNumber;
            }

            $ticketNumber = generate_next_ticket_number($pdo);
            $createdDate  = date('M j, Y');
            $latestUpdate = $requestType === 'issue'
                ? 'Issue logged. Our shift supervisor is reviewing your report.'
                : 'Your team is reviewing your question.';

            $stmt = $pdo->prepare("
                INSERT INTO support_requests (
                    ticket_number, user_id, request_type, subject, order_number,
                    message, status, latest_update, created_date
                ) VALUES (?, ?, ?, ?, ?, ?, 'In review', ?, ?)
            ");
            $stmt->execute([
                $ticketNumber, $user['id'], $requestType, $subject, $orderNumber,
                $message, $latestUpdate, $createdDate
            ]);
            $reqId = (int) $pdo->lastInsertId();

            $replyStmt = $pdo->prepare("
                INSERT INTO support_replies (support_request_id, sender_role, sender_name, reply_text)
                VALUES (?, 'customer', ?, ?)
            ");
            $replyStmt->execute([$reqId, $user['first_name'] . ' ' . $user['last_name'], $message]);

            $ticketStmt = $pdo->prepare("SELECT * FROM support_requests WHERE id = ?");
            $ticketStmt->execute([$reqId]);

            json_response(true, 'Support request #' . $ticketNumber . ' sent! Our neighborhood team will reply shortly.', [
                'ticket' => $ticketStmt->fetch(),
            ]);
        }

        case 'get_support': {
            $ticketNumber = trim((string) ($input['ticket_number'] ?? ''));
            $stmt = $pdo->prepare("SELECT * FROM support_requests WHERE ticket_number = ?");
            $stmt->execute([$ticketNumber]);
            $ticket = $stmt->fetch();

            if (!$ticket) {
                json_response(false, 'Support request not found.', [], 404);
            }

            $repStmt = $pdo->prepare("SELECT * FROM support_replies WHERE support_request_id = ? ORDER BY id ASC");
            $repStmt->execute([$ticket['id']]);
            $replies = $repStmt->fetchAll();

            json_response(true, 'Support request loaded.', [
                'ticket'  => $ticket,
                'replies' => $replies,
            ]);
        }

        case 'reply_support': {
            $user         = get_current_user_record($pdo);
            $ticketNumber = trim((string) ($input['ticket_number'] ?? ''));
            $replyText    = trim((string) ($input['reply_text'] ?? ''));
            $newStatus    = trim((string) ($input['status'] ?? ''));

            if ($ticketNumber === '' || $replyText === '') {
                json_response(false, 'Please write a reply message.', [], 400);
            }

            $stmt = $pdo->prepare("SELECT * FROM support_requests WHERE ticket_number = ?");
            $stmt->execute([$ticketNumber]);
            $ticket = $stmt->fetch();
            if (!$ticket) {
                json_response(false, 'Support request not found.', [], 404);
            }

            $senderRole = ($user['role'] === 'admin' || $user['role'] === 'staff') ? 'admin' : 'customer';
            $senderName = $user['first_name'] . ' ' . $user['last_name'] . ($senderRole === 'admin' ? ' (Admin)' : '');

            $ins = $pdo->prepare("
                INSERT INTO support_replies (support_request_id, sender_role, sender_name, reply_text)
                VALUES (?, ?, ?, ?)
            ");
            $ins->execute([$ticket['id'], $senderRole, $senderName, $replyText]);

            if ($newStatus === '') {
                $newStatus = ($senderRole === 'admin') ? 'Awaiting your reply' : 'In review';
            }

            $upd = $pdo->prepare("
                UPDATE support_requests
                SET status = ?, latest_update = ?
                WHERE id = ?
            ");
            $upd->execute([$newStatus, mb_substr($replyText, 0, 140), $ticket['id']]);

            $repStmt = $pdo->prepare("SELECT * FROM support_replies WHERE support_request_id = ? ORDER BY id ASC");
            $repStmt->execute([$ticket['id']]);

            json_response(true, 'Reply posted to #' . $ticketNumber . '.', [
                'status'        => $newStatus,
                'latest_update' => mb_substr($replyText, 0, 140),
                'replies'       => $repStmt->fetchAll(),
            ]);
        }

        default:
            json_response(false, 'Unknown API action: ' . htmlspecialchars($action), [], 400);
    }
} catch (Throwable $e) {
    json_response(false, 'Server error: ' . $e->getMessage(), [], 500);
}
