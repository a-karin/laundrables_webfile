<?php
require_once __DIR__ . '/config/db.php';

$pageTitle = "Admin Dashboard";
$activePage = "admin dashboard";

$pdo = get_db();

// Fetch admin user (Amir Wagas) or current logged-in staff
$stmtAdmin = $pdo->prepare("SELECT * FROM users WHERE role IN ('admin', 'staff') ORDER BY id ASC LIMIT 1");
$stmtAdmin->execute();
$adminUser = $stmtAdmin->fetch() ?: ['first_name' => 'Amir', 'last_name' => 'Wagas', 'role' => 'Admin'];
$adminFullName = trim($adminUser['first_name'] . ' ' . $adminUser['last_name']);

// Fetch all orders from DB
$allOrders = $pdo->query("SELECT * FROM orders ORDER BY id DESC")->fetchAll();

// Compute dynamic counts and payment statistics
$waitingCount   = 0;
$progressCount  = 0;
$readyCount     = 0;
$completedCount = 0;

$paidTodayTotal = 0.0;
$paidCount      = 0;
$unpaidTotal    = 0.0;
$unpaidCount    = 0;
$refundedTotal  = 0.0;

foreach ($allOrders as $ord) {
    switch ($ord['laundry_status']) {
        case 'Waiting':
            $waitingCount++;
            break;
        case 'In progress':
            $progressCount++;
            break;
        case 'Ready for pickup':
            $readyCount++;
            break;
        case 'Completed':
            $completedCount++;
            break;
    }

    $amt = (float) $ord['total_amount'];
    if ($ord['payment_status'] === 'Paid') {
        $paidTodayTotal += $amt;
        $paidCount++;
    } elseif ($ord['payment_status'] === 'Unpaid') {
        $unpaidTotal += $amt;
        $unpaidCount++;
    } elseif ($ord['payment_status'] === 'Refunded') {
        $refundedTotal += $amt;
    }
}

// Historical base + live paid orders for all-time collected
$allTimeCollected = 63591.00 + $paidTodayTotal;

// Fetch all customer support tickets so staff can respond
$allTickets = $pdo->query("
    SELECT s.*, u.first_name, u.last_name
    FROM support_requests s
    LEFT JOIN users u ON u.id = s.user_id
    ORDER BY s.id DESC
")->fetchAll();

function get_order_status_badge_class(string $status): string {
    switch ($status) {
        case 'Waiting':
            return 'status status-waiting';
        case 'In progress':
            return 'status status-progress';
        case 'Ready for pickup':
            return 'status status-ready';
        case 'Completed':
            return 'status status-completed';
        default:
            return 'status status-progress';
    }
}

function get_payment_badge_class(string $payment): string {
    return $payment === 'Paid' ? 'status status-paid' : 'status status-unpaid';
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laundrables | <?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="css/style.css?v=3">
    <style>
      .page-heading {
          display: flex;
          justify-content: space-between;
          align-items: center;
          gap: 20px;
          margin-bottom: 18px;
      }

      .page-heading h1 {
          margin: 0 0 6px;
          font-size: 28px;
          font-weight: 800;
          line-height: 1.2;
      }

      .page-heading p {
          margin: 0;
          color: var(--muted);
          font-size: 14px;
      }

      .stats-grid {
          display: grid;
          grid-template-columns: repeat(4, minmax(0, 1fr));
          gap: 14px;
          margin-bottom: 28px;
      }

      .stat-card {
          position: relative;
          min-width: 0;
          padding: 20px;
          background: #fff;
          border: 1px solid var(--border);
          border-bottom: 4px solid var(--green);
          border-radius: 13px;
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.025);
      }

      .stat-card:nth-child(1) {
          border-bottom-color: #d98a20;
      }

      .stat-card:nth-child(2) {
          border-bottom-color: #168bdb;
      }

      .stat-card:nth-child(3) {
          border-bottom-color: #087568;
      }

      .stat-card:nth-child(4) {
          border-bottom-color: #20b9b0;
      }

      .stat-card-heading {
          display: flex;
          justify-content: space-between;
          align-items: center;
          color: var(--muted);
          font-size: 13px;
          margin-bottom: 10px;
      }

      .stat-value {
          margin: 0 0 10px;
          font-size: 30px;
          line-height: 1.2;
          font-weight: 800;
          color: var(--text);
      }

      .stat-foot {
          margin: 0;
          color: var(--muted);
          font-size: 11px;
          line-height: 1.5;
      }

      .orders-section {
          margin-top: 20px;
      }

      .orders-toolbar {
          display: flex;
          align-items: center;
          gap: 12px;
          margin: 18px 0 16px;
          flex-wrap: wrap;
      }

      .orders-toolbar .search-box {
          flex: 1;
          min-width: 220px;
          display: flex;
          align-items: center;
          gap: 8px;
          height: 42px;
          padding: 0 12px;
          background: #fff;
          border: 1px solid var(--border);
          border-radius: 8px;
      }

      .orders-toolbar .search-box input {
          border: 0;
          outline: none;
          width: 100%;
          font-size: 13px;
          color: var(--text);
          background: transparent;
      }

      .orders-toolbar select {
          min-width: 0;
          height: 42px;
          padding: 0 12px;
          color: var(--text);
          background: #fff;
          border: 1px solid var(--border);
          border-radius: 8px;
          font: inherit;
          font-size: 13px;
          max-width: 200px;
      }

      .table-card {
          overflow: hidden;
          background: #fff;
          border: 1px solid var(--border);
          border-radius: 13px;
      }

      .table-scroll {
          width: 100%;
          overflow-x: auto;
      }

      .table-card table {
          width: 100%;
          min-width: 850px;
          border-collapse: collapse;
          text-align: left;
      }

      .table-card th {
          padding: 15px 14px;
          color: var(--muted);
          background: #f7faf9;
          font-size: 11px;
          font-weight: 600;
          white-space: nowrap;
      }

      .table-card td {
          padding: 16px 14px;
          border-top: 1px solid var(--border);
          color: var(--text);
          font-size: 12px;
          white-space: nowrap;
      }

      .table-card tbody tr:hover {
          background: #f8fbfa;
      }

      .status {
          display: inline-flex;
          align-items: center;
          gap: 6px;
          font-size: 12px;
          font-weight: 600;
      }

      .status::before {
          content: "";
          width: 7px;
          height: 7px;
          flex-shrink: 0;
          border-radius: 50%;
          background: currentColor;
      }

      .status-waiting {
          color: #b76a08;
      }

      .status-progress {
          color: #2877b8;
      }

      .status-ready {
          color: #087568;
      }

      .status-completed {
          color: #07867b;
      }

      .status-paid {
          color: #087568;
      }

      .status-unpaid {
          color: #b76a08;
      }

      .action-link {
          color: var(--green);
          font-size: 12px;
          font-weight: 600;
          text-decoration: none;
          white-space: nowrap;
          cursor: pointer;
      }

      .action-link:hover {
          text-decoration: underline;
      }

      .table-footer {
          display: flex;
          justify-content: space-between;
          align-items: center;
          gap: 12px;
          padding: 14px 16px;
          border-top: 1px solid var(--border);
          color: var(--muted);
          font-size: 11px;
      }

      .pagination {
          display: flex;
          align-items: center;
          gap: 8px;
      }

      .pagination a,
      .pagination span {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-width: 24px;
          min-height: 24px;
          color: var(--green);
          text-decoration: none;
          border-radius: 5px;
          padding: 0 6px;
      }

      .pagination .active {
          color: #fff;
          background: var(--green);
      }

      .payments-section {
          margin-top: 24px;
          padding: 18px;
          background: #fff;
          border: 1px solid var(--border);
          border-radius: 13px;
      }

      .payments-section > h2 {
          margin: 0 0 14px;
          color: var(--text);
          font-size: 23px;
          font-weight: 700;
      }

      .payments-grid {
          display: grid;
          grid-template-columns: repeat(4, minmax(0, 1fr));
          gap: 12px;
      }

      .payment-card {
          display: flex;
          flex-direction: column;
          align-items: flex-start;
          min-width: 0;
          padding: 18px;
          background: #fff;
          border: 1px solid var(--border);
          border-radius: 12px;
      }

      .payment-label {
          display: flex;
          justify-content: space-between;
          align-items: center;
          gap: 8px;
          width: 100%;
          margin-bottom: 10px;
          color: var(--muted);
          font-size: 12px;
      }

      .payment-label span {
          color: var(--green);
          font-size: 15px;
      }

      .payment-value {
          margin-bottom: 10px;
          color: var(--text);
          font-size: 25px;
          font-weight: 800;
          line-height: 1.2;
          overflow-wrap: anywhere;
      }

      .payment-card p {
          margin: 0;
          color: var(--muted);
          font-size: 11px;
          line-height: 1.5;
      }
    </style>
  </head>
    <body>
      <div class="app">
        <aside class="sidebar">
            <h2>◉ Laundrables</h2>
            <p class="workspace">STAFF WORKSPACE</p>
            <nav>
                <a class="active" href="#dashboard">› Dashboard</a>
                <a href="#orders">Orders Queue</a>
                <a href="#payments">Payments</a>
                <a href="#support-tickets">Support Tickets</a>
                <a href="index.php">Customer View ↗</a>
                <a href="logout.php">↪ Sign out</a>
            </nav>
        </aside>

        <main class="main">
          <header class="topbar">
              <span class="shop-status">
                  ● Shop open
              </span>

              <div class="profile">
                  <strong><?= htmlspecialchars($adminFullName) ?></strong>
                  <small>Admin</small>
              </div>
          </header>

          <div class="content" id="dashboard">
              <section class="welcome page-heading">
              <div>
                <p class="date-label"><?= date('l, F j, Y') ?></p>
                <h1>Let’s make today run smoothly</h1>
                <p class="subtitle">Here’s what’s happening at your laundry shop today.</p>
              </div>
              <a class="button button-primary" href="#create-order" id="open-create-order-modal">+ Create order</a>
            </section>

            <section class="stats-grid">
              <article class="stat-card waiting-card">
                <div class="stat-card-heading">Waiting for clothes <span>◷</span></div>
                <div class="stat-value"><?= $waitingCount ?></div>
                <p class="stat-foot">Orders waiting to start</p>
              </article>
              <article class="stat-card progress-card">
                <div class="stat-card-heading">In progress <span>▣</span></div>
                <div class="stat-value"><?= $progressCount ?></div>
                <p class="stat-foot">Currently being processed</p>
              </article>
              <article class="stat-card ready-card">
                <div class="stat-card-heading">Ready for pick up <span>▣</span></div>
                <div class="stat-value"><?= $readyCount ?></div>
                <p class="stat-foot">Finished and ready</p>
              </article>
              <article class="stat-card completed-card">
                <div class="stat-card-heading">Completed <span class="check-icon">✓</span></div>
                <div class="stat-value"><?= $completedCount ?></div>
                <p class="stat-foot">Orders picked up &amp; completed</p>
              </article>
            </section>

            <section class="orders-section" id="orders">
              <div class="section-heading">
                <div>
                  <h2>Order Queue &amp; Management</h2>
                  <p class="subtitle">Search, filter, or click an order to update its laundry or payment status.</p>
                </div>
              </div>

              <div class="orders-toolbar">
                <label class="search-box">
                    <span>⌕</span>
                    <input type="search" id="admin-search-input" placeholder="Search order number or customer">
                </label>
                <select id="admin-service-filter" aria-label="Service filter">
                    <option value="All services">All services</option>
                    <option value="Wash & Fold">Wash &amp; Fold</option>
                    <option value="Dry Clean">Dry Clean</option>
                    <option value="Special Care">Special Care</option>
                </select>
                <select id="admin-queue-filter" aria-label="Queue filter">
                    <option value="All orders">All orders</option>
                    <option value="Today's queue">Today’s queue</option>
                    <option value="Waiting">Waiting</option>
                    <option value="In progress">In progress</option>
                    <option value="Ready for pickup">Ready for pickup</option>
                    <option value="Completed">Completed</option>
                </select>
              </div>

              <div class="table-card">
                <div class="table-scroll">
                  <table>
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Weight / Items</th>
                            <th>Amount</th>
                            <th>Laundry status</th>
                            <th>Payment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="admin-orders-tbody">
                      <?php foreach ($allOrders as $ord): ?>
                        <tr data-order-row
                            data-order-number="<?= htmlspecialchars($ord['order_number']) ?>"
                            data-customer-name="<?= htmlspecialchars($ord['customer_name']) ?>"
                            data-service-name="<?= htmlspecialchars($ord['service_name']) ?>"
                            data-laundry-status="<?= htmlspecialchars($ord['laundry_status']) ?>"
                            data-today-queue="<?= (int) $ord['is_today_queue'] ?>">
                            <td><b><?= htmlspecialchars($ord['order_number']) ?></b></td>
                            <td><?= htmlspecialchars($ord['customer_name']) ?></td>
                            <td><?= htmlspecialchars($ord['service_name']) ?></td>
                            <td><?= htmlspecialchars(format_quantity_unit((float) $ord['quantity'], $ord['unit_type'])) ?></td>
                            <td><?= htmlspecialchars(format_php((float) $ord['total_amount'])) ?></td>
                            <td><span class="<?= get_order_status_badge_class($ord['laundry_status']) ?>"><?= htmlspecialchars($ord['laundry_status']) ?></span></td>
                            <td><span class="<?= get_payment_badge_class($ord['payment_status']) ?>"><?= htmlspecialchars($ord['payment_status']) ?></span></td>
                            <td><a href="#edit-order" class="action-link" data-edit-order="<?= (int) $ord['id'] ?>">View / Update ↗</a></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <footer class="table-footer">
                    <span id="admin-table-count">Showing <b>1–<?= count($allOrders) ?></b> of <b><?= count($allOrders) ?></b> orders</span>
                    <nav class="pagination" id="admin-pagination"></nav>
                </footer>
              </div>
            </section>

            <section class="payments-section" id="payments">
              <h2>Payment records</h2>
              <div class="payments-grid">
                <article class="payment-card">
                    <div class="payment-label">Total collected <span>▤</span></div>
                    <div class="payment-value"><?= htmlspecialchars(format_php($allTimeCollected)) ?></div>
                    <p>All-time cash and card payments</p>
                </article>
                <article class="payment-card">
                    <div class="payment-label">Collected from orders <span>⊙</span></div>
                    <div class="payment-value"><?= htmlspecialchars(format_php($paidTodayTotal)) ?></div>
                    <p><?= $paidCount ?> paid transactions</p>
                </article>
                <article class="payment-card">
                    <div class="payment-label">Outstanding balance <span>◷</span></div>
                    <div class="payment-value"><?= htmlspecialchars(format_php($unpaidTotal)) ?></div>
                    <p><?= $unpaidCount ?> orders awaiting payment</p>
                </article>
                <article class="payment-card">
                    <div class="payment-label">Refunds recorded <span>⟲</span></div>
                    <div class="payment-value"><?= htmlspecialchars(format_php($refundedTotal)) ?></div>
                    <p><?= $refundedTotal > 0 ? 'Processed refunds' : 'No refunds recorded' ?></p>
                </article>
              </div>
            </section>

            <section class="orders-section" id="support-tickets" style="margin-top:28px;">
                <div class="section-heading">
                    <div>
                        <h2>Customer Support Tickets</h2>
                        <p class="subtitle">Review and reply to customer questions and issue reports.</p>
                    </div>
                </div>
                <div class="table-card" style="margin-top:14px;">
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Customer</th>
                                    <th>Subject</th>
                                    <th>Related Order</th>
                                    <th>Status</th>
                                    <th>Latest Update</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allTickets as $t): ?>
                                    <tr>
                                        <td><b>#<?= htmlspecialchars($t['ticket_number']) ?></b></td>
                                        <td><?= htmlspecialchars(trim(($t['first_name'] ?? 'Troy') . ' ' . ($t['last_name'] ?? 'Pogi'))) ?></td>
                                        <td><?= htmlspecialchars($t['subject']) ?></td>
                                        <td><?= htmlspecialchars($t['order_number'] ?: '—') ?></td>
                                        <td><span class="status <?= $t['status'] === 'Resolved' ? 'status-completed' : 'status-waiting' ?>"><?= htmlspecialchars($t['status']) ?></span></td>
                                        <td><?= htmlspecialchars($t['latest_update']) ?></td>
                                        <td><a href="#reply-ticket" class="action-link" data-request="<?= htmlspecialchars($t['ticket_number']) ?>">Respond ↗</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <footer class="page-footer">© 2026 Laundrables · Made for your everyday</footer>
          </div>
        </main>
      </div>

      <!-- Admin Create Order Modal -->
      <div class="modal-backdrop" id="admin-create-order-modal">
          <div class="modal-card">
              <div class="modal-header">
                  <h3>Create New Order</h3>
                  <button type="button" class="modal-close" data-close-modal>×</button>
              </div>
              <form id="admin-create-order-form">
                  <div class="form-group">
                      <label for="create-customer-name">Customer name *</label>
                      <input type="text" id="create-customer-name" placeholder="e.g. Troy Pogi" required>
                  </div>
                  <div class="form-row">
                      <div class="form-group">
                          <label for="create-service-name">Service</label>
                          <select id="create-service-name" style="height:39px;padding:0 10px;border:1px solid var(--border);border-radius:6px;">
                              <option value="Wash & Fold">Wash &amp; Fold</option>
                              <option value="Dry Clean">Dry Clean</option>
                              <option value="Special Care">Special Care</option>
                          </select>
                      </div>
                      <div class="form-group">
                          <label for="create-quantity">Weight (kg) / Items</label>
                          <input type="number" id="create-quantity" step="0.5" min="1" value="5.0" required>
                      </div>
                  </div>
                  <div class="form-row">
                      <div class="form-group">
                          <label for="create-total-amount">Amount (₱)</label>
                          <input type="number" id="create-total-amount" step="0.01" min="0" value="500.00" required>
                      </div>
                      <div class="form-group">
                          <label for="create-laundry-status">Laundry status</label>
                          <select id="create-laundry-status" style="height:39px;padding:0 10px;border:1px solid var(--border);border-radius:6px;">
                              <option value="Waiting">Waiting</option>
                              <option value="In progress">In progress</option>
                              <option value="Ready for pickup">Ready for pickup</option>
                              <option value="Completed">Completed</option>
                          </select>
                      </div>
                  </div>
                  <div class="form-group">
                      <label for="create-payment-status">Payment status</label>
                      <select id="create-payment-status" style="height:39px;padding:0 10px;border:1px solid var(--border);border-radius:6px;">
                          <option value="Unpaid">Unpaid</option>
                          <option value="Paid">Paid</option>
                      </select>
                  </div>
                  <div class="form-group">
                      <label for="create-care-instructions">Care instructions</label>
                      <input type="text" id="create-care-instructions" placeholder="Optional notes">
                  </div>
                  <div class="modal-actions">
                      <button type="button" class="secondary" data-close-modal>Cancel</button>
                      <button type="submit" class="button">Create order</button>
                  </div>
              </form>
          </div>
      </div>

      <!-- Admin Edit / Update / Delete Order Modal -->
      <div class="modal-backdrop" id="admin-edit-order-modal">
          <div class="modal-card">
              <div class="modal-header">
                  <h3 id="edit-order-title">Manage Order</h3>
                  <button type="button" class="modal-close" data-close-modal>×</button>
              </div>
              <form id="admin-edit-order-form">
                  <input type="hidden" id="edit-order-id" value="">
                  <div class="form-group">
                      <label for="edit-customer-name">Customer name *</label>
                      <input type="text" id="edit-customer-name" required>
                  </div>
                  <div class="form-row">
                      <div class="form-group">
                          <label for="edit-service-name">Service</label>
                          <select id="edit-service-name" style="height:39px;padding:0 10px;border:1px solid var(--border);border-radius:6px;">
                              <option value="Wash & Fold">Wash &amp; Fold</option>
                              <option value="Dry Clean">Dry Clean</option>
                              <option value="Special Care">Special Care</option>
                          </select>
                      </div>
                      <div class="form-group">
                          <label for="edit-quantity">Weight / Items</label>
                          <input type="number" id="edit-quantity" step="0.5" min="1" required>
                      </div>
                  </div>
                  <div class="form-row">
                      <div class="form-group">
                          <label for="edit-total-amount">Amount (₱)</label>
                          <input type="number" id="edit-total-amount" step="0.01" min="0" required>
                      </div>
                      <div class="form-group">
                          <label for="edit-pickup-estimate">Pickup estimate</label>
                          <input type="text" id="edit-pickup-estimate">
                      </div>
                  </div>
                  <div class="form-row">
                      <div class="form-group">
                          <label for="edit-laundry-status">Laundry status</label>
                          <select id="edit-laundry-status" style="height:39px;padding:0 10px;border:1px solid var(--border);border-radius:6px;">
                              <option value="Waiting">Waiting</option>
                              <option value="In progress">In progress</option>
                              <option value="Ready for pickup">Ready for pickup</option>
                              <option value="Completed">Completed</option>
                          </select>
                      </div>
                      <div class="form-group">
                          <label for="edit-payment-status">Payment status</label>
                          <select id="edit-payment-status" style="height:39px;padding:0 10px;border:1px solid var(--border);border-radius:6px;">
                              <option value="Unpaid">Unpaid</option>
                              <option value="Paid">Paid</option>
                              <option value="Refunded">Refunded</option>
                          </select>
                      </div>
                  </div>
                  <div class="form-group">
                      <label for="edit-care-instructions">Care instructions</label>
                      <input type="text" id="edit-care-instructions">
                  </div>
                  <div class="modal-actions" style="justify-content:space-between;">
                      <button type="button" class="btn-danger" id="admin-delete-order-btn">Delete order</button>
                      <div style="display:flex;gap:10px;">
                          <button type="button" class="secondary" data-close-modal>Cancel</button>
                          <button type="submit" class="button">Save updates</button>
                      </div>
                  </div>
              </form>
          </div>
      </div>

      <!-- Admin Support Ticket Reply Modal -->
      <div class="modal-backdrop" id="support-ticket-modal">
          <div class="modal-card">
              <div class="modal-header">
                  <h3 id="modal-ticket-title">Support Request</h3>
                  <button type="button" class="modal-close" data-close-modal>×</button>
              </div>
              <p id="modal-ticket-meta" style="font-size:12px;margin-top:0;color:var(--muted);"></p>
              <div class="reply-thread" id="modal-ticket-thread"></div>
              <form id="support-reply-form">
                  <input type="hidden" id="reply-ticket-number" value="">
                  <div class="form-group">
                      <label for="reply-ticket-status">Update ticket status</label>
                      <select id="reply-ticket-status" style="height:39px;padding:0 10px;border:1px solid var(--border);border-radius:6px;">
                          <option value="In review">In review</option>
                          <option value="Awaiting your reply">Awaiting your reply</option>
                          <option value="Resolved">Resolved</option>
                      </select>
                  </div>
                  <div class="form-group">
                      <label for="reply-ticket-text">Staff reply *</label>
                      <input type="text" id="reply-ticket-text" placeholder="Write reply to customer..." required>
                  </div>
                  <div class="modal-actions">
                      <button type="button" class="secondary" data-close-modal>Close</button>
                      <button type="submit" class="button">Post reply &amp; update</button>
                  </div>
              </form>
          </div>
      </div>

      <script src="js/app.js"></script>
    </body>
</html>
