<?php
require_once __DIR__ . '/config/db.php';

$pageTitle = "Dashboard";
$activePage = "dashboard";

$pdo = get_db();
$user = get_current_user_record($pdo, 'customer');
$fullName = trim($user['first_name'] . ' ' . $user['last_name']);

// Automatic stats queries for this customer
$stmtStats = $pdo->prepare("
    SELECT
        SUM(CASE WHEN laundry_status IN ('Waiting', 'In progress') THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN laundry_status = 'Ready for pickup' THEN 1 ELSE 0 END) AS ready_count,
        SUM(CASE WHEN laundry_status = 'Completed' THEN 1 ELSE 0 END) AS completed_count
    FROM orders
    WHERE user_id = ? OR LOWER(customer_name) = LOWER(?)
");
$stmtStats->execute([$user['id'], $fullName]);
$stats = $stmtStats->fetch() ?: ['active_count' => 0, 'ready_count' => 0, 'completed_count' => 0];

$activeCount    = (int) ($stats['active_count'] ?? 0);
$readyCount     = (int) ($stats['ready_count'] ?? 0);
$completedCount = (int) ($stats['completed_count'] ?? 0);

// Fetch latest active / ready order for the customer
$stmtActive = $pdo->prepare("
    SELECT * FROM orders
    WHERE (user_id = ? OR LOWER(customer_name) = LOWER(?))
      AND laundry_status != 'Completed'
    ORDER BY id DESC
    LIMIT 1
");
$stmtActive->execute([$user['id'], $fullName]);
$activeOrder = $stmtActive->fetch();

// Fetch recently completed orders for the history table
$stmtHistory = $pdo->prepare("
    SELECT * FROM orders
    WHERE (user_id = ? OR LOWER(customer_name) = LOWER(?))
    ORDER BY id DESC
    LIMIT 10
");
$stmtHistory->execute([$user['id'], $fullName]);
$historyOrders = $stmtHistory->fetchAll();

// Determine progress steps filled (1 to 4)
$progressFilled = 0;
if ($activeOrder) {
    $statusMap = [
        'Waiting'          => 1,
        'In progress'      => 2,
        'Ready for pickup' => 3,
        'Completed'        => 4,
    ];
    $progressFilled = $statusMap[$activeOrder['laundry_status']] ?? 2;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Laundrables | <?= htmlspecialchars($pageTitle) ?></title>
        <link rel="stylesheet" href="css/style.css?v=3">
    </head>
    <body>
        <div class="app">
            <aside class="sidebar">
                <h2>◉ Laundrables</h2>
                <p class="workspace">CUSTOMER WORKSPACE</p>

                <nav>
                    <a class="active" href="index.php">› Dashboard</a>
                    <a href="new-order.php">New Order</a>
                    <a href="profile.php">Profile</a>
                    <a href="service.php">Help &amp; Support</a>
                    <?php if (in_array($user['role'], ['admin', 'staff'], true)): ?><a href="admin.php">← Back to Admin</a><?php endif; ?>
                    <a href="logout.php">↪ Sign out</a>
                </nav>
            </aside>

            <main class="main">
                <header class="topbar">
                    <span class="shop-status">● Shop open</span>
                    <span id="profile">
                        <strong><?= htmlspecialchars($fullName) ?></strong>
                        <small><?= htmlspecialchars(ucfirst($user['role'])) ?></small>
                    </span>
                </header>

                <div class="content">
                    <section class="welcome">
                        <div>
                            <?php
                                $hour = (int) date('G');
                                $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
                            ?>
                            <h1><?= $greeting ?>, <?= htmlspecialchars($user['first_name']) ?></h1>
                            <p>A little less laundry. A little more life.</p>
                        </div>
                        <a class="button" href="new-order.php">+ New order</a>
                    </section>

                    <!-- Automatic stats updated from database -->
                    <section class="stats">
                        <article class="card">
                            <p>Active orders</p>
                            <h2><?= $activeCount ?></h2>
                            <small><?= $activeCount > 0 ? 'Your load is in the wash' : 'No loads currently in the wash' ?></small>
                        </article>
                        <article class="card">
                            <p>Ready for pickup</p>
                            <h2><?= $readyCount ?></h2>
                            <small><?= $readyCount > 0 ? 'Fresh, folded, and waiting' : 'Nothing waiting at the counter' ?></small>
                        </article>
                        <article class="card">
                            <p>Completed orders</p>
                            <h2><?= $completedCount ?></h2>
                            <small><?= $completedCount ?> little fresh starts</small>
                        </article>
                    </section>

                    <section class="order-layout">
                        <?php if ($activeOrder): ?>
                            <article class="card" id="active-order">
                                <h3>Your active order</h3>
                                <h2><?= htmlspecialchars($activeOrder['order_number']) ?></h2>
                                <p>
                                    <?= htmlspecialchars($activeOrder['laundry_status']) ?>
                                    · <?= htmlspecialchars(format_quantity_unit((float) $activeOrder['quantity'], $activeOrder['unit_type'])) ?>
                                    · <?= htmlspecialchars(format_php((float) $activeOrder['total_amount'])) ?>
                                </p>
                                <div class="progress">
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                        <span class="<?= $i <= $progressFilled ? 'filled' : '' ?>"></span>
                                    <?php endfor; ?>
                                </div>
                                <p>We're giving your everyday a fresh start.</p>
                                <hr>
                                <p>Pickup · <?= htmlspecialchars($activeOrder['pickup_estimate']) ?></p>
                                <button type="button" class="secondary" id="track-order-btn">Track order →</button>
                            </article>
                        <?php else: ?>
                            <article class="card" id="active-order">
                                <h3>Your active order</h3>
                                <h2>No active orders right now</h2>
                                <p>Whenever your basket is full, book a drop-off and track every step here.</p>
                                <hr>
                                <a class="button" href="new-order.php">+ Book a new order</a>
                            </article>
                        <?php endif; ?>

                        <article class="promo">
                            <h3>One less thing on your list</h3>
                            <div class="sparkle">✧</div>
                            <h2>Ready for your next fresh start?</h2>
                            <p>Book your next load in a minute.</p>
                            <a class="button" href="new-order.php">+ Quick order</a>
                            <small>Wash &amp; Fold ₱60.00 / kg</small>
                        </article>
                    </section>

                    <section id="history" class="history">
                        <h2>Recent &amp; completed orders</h2>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Date / Status</th>
                                        <th>Service</th>
                                        <th>Weight / Items</th>
                                        <th>Total</th>
                                        <th>Payment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historyOrders as $ord): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($ord['order_number']) ?></strong></td>
                                            <td><?= htmlspecialchars($ord['completed_date'] ?: $ord['dropoff_date']) ?> (<?= htmlspecialchars($ord['laundry_status']) ?>)</td>
                                            <td><?= htmlspecialchars($ord['service_name']) ?></td>
                                            <td><?= htmlspecialchars(format_quantity_unit((float) $ord['quantity'], $ord['unit_type'])) ?></td>
                                            <td><?= htmlspecialchars(format_php((float) $ord['total_amount'])) ?></td>
                                            <td>● <?= htmlspecialchars($ord['payment_status']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <footer>© 2026 Laundrables · Made for your everyday</footer>
                </div>
            </main>
        </div>

        <?php if ($activeOrder): ?>
        <div class="modal-backdrop" id="track-order-modal">
            <div class="modal-card">
                <div class="modal-header">
                    <h3>Order <?= htmlspecialchars($activeOrder['order_number']) ?> · Live Tracker</h3>
                    <button type="button" class="modal-close" data-close-modal>×</button>
                </div>
                <p style="font-size:13px;margin-top:0;">
                    <strong>Service:</strong> <?= htmlspecialchars($activeOrder['service_name']) ?>
                    (<?= htmlspecialchars(format_quantity_unit((float) $activeOrder['quantity'], $activeOrder['unit_type'])) ?>)
                    <br>
                    <strong>Status:</strong> <?= htmlspecialchars($activeOrder['laundry_status']) ?>
                    · <strong>Payment:</strong> <?= htmlspecialchars($activeOrder['payment_status']) ?>
                    <br>
                    <strong>Drop-off:</strong> <?= htmlspecialchars($activeOrder['dropoff_date']) ?> (<?= htmlspecialchars($activeOrder['dropoff_time']) ?>)
                    <br>
                    <strong>Estimated Pickup:</strong> <?= htmlspecialchars($activeOrder['pickup_estimate']) ?>
                </p>

                <form id="customer-update-order-form">
                    <input type="hidden" id="track-order-id" value="<?= (int) $activeOrder['id'] ?>">
                    <div class="form-group">
                        <label for="track-care-instructions">Update care instructions</label>
                        <textarea id="track-care-instructions" class="field" style="width:100%;min-height:80px;padding:10px;border:1px solid var(--border);border-radius:8px;"><?= htmlspecialchars((string) $activeOrder['care_instructions']) ?></textarea>
                    </div>
                    <div id="track-order-message" class="alert-banner success" style="display:none;"></div>
                    <div class="modal-actions">
                        <button type="button" class="secondary" data-close-modal>Close</button>
                        <button type="submit" class="button">Save instructions</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <script src="js/app.js"></script>
    </body>
</html>
