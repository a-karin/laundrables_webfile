<?php
require_once __DIR__ . '/config/db.php';

$pageTitle = "Help & Support";
$activePage = "help & support";

$pdo = get_db();
$user = get_current_user_record($pdo, 'customer');
$fullName = trim($user['first_name'] . ' ' . $user['last_name']);
$shopAddress = get_shop_address($pdo);

// Fetch customer support requests from database
$stmtReq = $pdo->prepare("
    SELECT * FROM support_requests
    WHERE user_id = ?
    ORDER BY id DESC
");
$stmtReq->execute([$user['id']]);
$supportRequests = $stmtReq->fetchAll();

function get_support_status_class(string $status): string {
    switch ($status) {
        case 'Awaiting your reply':
            return 'status-waiting';
        case 'Resolved':
            return 'status-resolved';
        default:
            return 'status-review';
    }
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
        .support-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 310px;
            gap: 20px;
            align-items: start;
        }

        .support-left,
        .support-right {
            display: grid;
            gap: 18px;
            min-width: 0;
        }

        .support-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 24px;
            min-width: 0;
        }

        .support-card h2 {
            font-size: 18px;
            margin: 0 0 14px;
        }

        .support-description {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 20px;
        }

        .support-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 9px;
        }

        .support-field {
            margin-bottom: 18px;
        }

        .support-field input,
        .support-field select,
        .support-field textarea {
            display: block;
            width: 100%;
            box-sizing: border-box;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            color: var(--text);
            font: inherit;
            font-size: 13px;
        }

        .support-field textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.6;
        }

        .support-field input:focus,
        .support-field select:focus,
        .support-field textarea:focus {
            outline: none;
            border-color: var(--green);
            box-shadow: 0 0 0 3px rgba(18, 101, 96, .1);
        }

        .request-types {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .request-type {
            display: flex;
            align-items: center;
            gap: 9px;
            min-height: 44px;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
        }

        .request-type:has(input:checked) {
            background: var(--mint);
            border-color: var(--green);
            color: var(--green);
        }

        .request-type input {
            accent-color: var(--green);
            margin: 0;
        }

        .support-divider {
            border: 0;
            border-top: 1px solid var(--border);
            margin: 20px 0;
        }

        .support-button {
            display: inline-block;
            border: 1px solid var(--green);
            border-radius: 7px;
            padding: 12px 15px;
            background: var(--green);
            color: white;
            font: inherit;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .support-button:hover {
            background: #0e514d;
        }

        .support-button-outline {
            display: inline-block;
            padding: 10px 13px;
            background: white;
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .support-required {
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 11px;
        }

        .team-card {
            background: var(--mint);
        }

        .team-phone {
            display: block;
            margin: 12px 0;
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }

        .team-details {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.8;
            margin-top: 14px;
        }

        .team-details strong {
            display: block;
            color: var(--text);
            margin-bottom: 5px;
        }

        .team-link {
            display: inline-block;
            margin-top: 10px;
            color: var(--green);
            font-size: 12px;
            font-weight: 700;
        }

        .faq-item {
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
        }

        .faq-item:first-of-type {
            padding-top: 4px;
        }

        .faq-item:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .faq-item summary {
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.5;
        }

        .faq-item p {
            font-size: 12px;
            line-height: 1.7;
            margin: 10px 0 0;
        }

        .support-history {
            margin-top: 34px;
        }

        .support-history-heading {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 15px;
            margin-bottom: 16px;
        }

        .support-history-heading h2 {
            font-size: 20px;
            margin: 0 0 7px;
        }

        .support-history-heading p {
            font-size: 13px;
            margin: 0;
        }

        .support-table-wrap {
            background: white;
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow-x: auto;
        }

        .support-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            min-width: 650px;
        }

        .support-table th,
        .support-table td {
            padding: 17px 15px;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
            vertical-align: middle;
        }

        .support-table th {
            background: #f5f9f7;
            color: var(--muted);
            font-weight: 500;
        }

        .support-table tbody tr:last-child td {
            border-bottom: 0;
        }

        .request-title {
            display: block;
            color: var(--text);
            font-weight: 700;
            margin-bottom: 6px;
        }

        .request-meta {
            color: var(--muted);
            font-size: 11px;
        }

        .request-status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 11px;
            white-space: nowrap;
            margin-bottom: 6px;
        }

        .status-review {
            background: #eaf2fc;
            color: #326a9c;
        }

        .status-waiting {
            background: #fff2dc;
            color: #996000;
        }

        .status-resolved {
            background: #e4f5ef;
            color: #167360;
        }

        .request-update {
            display: block;
            color: var(--muted);
            line-height: 1.6;
        }

        .request-action {
            color: var(--green);
            font-weight: 700;
            text-decoration: none;
            white-space: nowrap;
        }

        .support-table-footer {
            padding: 13px 15px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 11px;
        }

        .support-message {
            display: none;
            background: var(--mint);
            color: var(--green-dark);
            border-radius: 8px;
            padding: 12px;
            margin-top: 15px;
            font-size: 13px;
            line-height: 1.5;
        }
    </style>
</head>

<body>
<div class="app">
    <aside class="sidebar">
        <h2>◉ Laundrables</h2>
        <p class="workspace">CUSTOMER WORKSPACE</p>
        <nav>
            <a href="index.php">Dashboard</a>
            <a href="new-order.php">New Order</a>
            <a href="profile.php">Profile</a>
            <a class="active" href="service.php">› Help &amp; Support</a>
            <?php if (in_array($user['role'], ['admin', 'staff'], true)): ?><a href="admin.php">← Back to Admin</a><?php endif; ?>
            <a href="logout.php">↪ Sign out</a>
        </nav>
    </aside>

    <!-- Main area -->
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
                    <h1>Help &amp; Support</h1>
                    <p>A little help, a little less worry. Your neighborhood team is here for you.</p>
                </div>
            </section>

            <div class="support-grid">

                <!-- Request form -->
                <div class="support-left">
                    <section class="support-card">
                        <h2>How can we help?</h2>

                        <p class="support-description">
                            Tell us what's on your mind. We'll keep your
                            request and replies together here.
                        </p>

                        <form id="support-form">

                            <span class="support-label">I'd like to... *</span>

                            <div class="request-types">
                                <label class="request-type">
                                    <input type="radio" name="request_type"
                                           value="issue" required>
                                    <span>Report an issue</span>
                                </label>

                                <label class="request-type">
                                    <input type="radio" name="request_type"
                                           value="help" checked>
                                    <span>Ask for help</span>
                                </label>
                            </div>

                            <div class="support-field">
                                <label class="support-label" for="subject">
                                    Subject *
                                </label>
                                <input type="text" id="subject" name="subject"
                                       placeholder="What do you need help with?"
                                       required>
                            </div>

                            <div class="support-field">
                                <label class="support-label" for="order-number">
                                    Related order (optional)
                                </label>
                                <input type="text" id="order-number"
                                       name="order_number"
                                       placeholder="e.g. LD-1048">
                            </div>

                            <div class="support-field">
                                <label class="support-label" for="message">
                                    Message *
                                </label>
                                <textarea id="message" name="message"
                                    placeholder="Tell us how we can help..."
                                    required></textarea>
                            </div>

                            <hr class="support-divider">

                            <button class="support-button" type="submit">
                                Send support request →
                            </button>

                            <p class="support-required">* Required fields</p>

                            <div class="support-message"
                                 id="support-message" role="status"></div>
                        </form>
                    </section>
                </div>

                <!-- Contact and FAQs -->
                <div class="support-right">

                    <section class="support-card team-card">
                        <h2>Your neighborhood team</h2>

                        <p class="support-description">
                            Prefer to talk? Call or stop by the shop
                            for help with your laundry.
                        </p>

                        <a class="team-phone" href="tel:+14155550148">
                            (415) 555-0148
                        </a>

                        <a class="support-button-outline"
                           href="tel:+14155550148">
                            Call the shop ↗
                        </a>

                        <hr class="support-divider">

                        <div class="team-details">
                            <strong>Laundrables</strong>
                            <?= htmlspecialchars($shopAddress['line']) ?>

                            <br>

                            ◷ Mon–Sat 8 AM–8 PM
                            <br>
                            &nbsp;&nbsp;&nbsp; Sunday 9 AM–5 PM

                            <br>

                            <a class="team-link"
                               href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($shopAddress['line'] . ', Philippines') ?>"
                               target="_blank" rel="noopener noreferrer">
                                Get directions ↗
                            </a>
                        </div>
                    </section>

                    <section class="support-card">
                        <h2>A few quick answers</h2>

                        <details class="faq-item">
                            <summary>Where can I check my pickup time?</summary>
                            <p>
                                Open Dashboard and select Track order on your active load to see
                                its progress and estimated ready time.
                            </p>
                        </details>

                        <details class="faq-item">
                            <summary>How do I pay for my order?</summary>
                            <p>
                                Check the payment instructions for your order.
                                Available payment methods and any balance due
                                should be confirmed with the shop.
                            </p>
                        </details>

                        <details class="faq-item">
                            <summary>How do I update care preferences?</summary>
                            <p>
                                Visit Profile to update your preferences for
                                future orders. For a current order, click Track order
                                on your Dashboard to edit care instructions.
                            </p>
                        </details>
                    </section>

                </div>
            </div>

            <!-- Support request history -->
            <section class="support-history">

                <div class="support-history-heading">
                    <div>
                        <h2>Your support requests</h2>
                        <p>Follow the conversation, check updates, or send your team a reply.</p>
                    </div>
                </div>

                <div class="support-table-wrap">
                    <table class="support-table">
                        <thead>
                            <tr>
                                <th>Request</th>
                                <th>Status &amp; latest update</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($supportRequests as $req): ?>
                                <tr>
                                    <td>
                                        <span class="request-title">
                                            <?= htmlspecialchars($req['subject']) ?>
                                        </span>
                                        <span class="request-meta">
                                            #<?= htmlspecialchars($req['ticket_number']) ?> · <?= htmlspecialchars($req['created_date']) ?>
                                            <?= !empty($req['order_number']) ? ' · Order ' . htmlspecialchars($req['order_number']) : '' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="request-status <?= get_support_status_class($req['status']) ?>">
                                            <?= htmlspecialchars($req['status']) ?>
                                        </span>
                                        <span class="request-update">
                                            <?= htmlspecialchars($req['latest_update']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="#request-details"
                                           class="request-action"
                                           data-request="<?= htmlspecialchars($req['ticket_number']) ?>">
                                            <?= $req['status'] === 'Awaiting your reply' ? 'Reply →' : 'View request →' ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="support-table-footer">
                        Showing all <?= count($supportRequests) ?> requests · Open requests include those awaiting your reply
                    </div>
                </div>

            </section>

            <footer>
                © 2026 Laundrables · Made for your everyday
            </footer>

        </div>
    </main>
</div>

<!-- Support Request Conversation & Reply Modal -->
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
            <div class="support-field" style="margin-bottom:12px;">
                <label class="support-label" for="reply-ticket-text">Send a reply</label>
                <textarea id="reply-ticket-text" placeholder="Write a message to the shop team..." style="min-height:80px;" required></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="support-button-outline" data-close-modal>Close</button>
                <button type="submit" class="support-button">Send reply →</button>
            </div>
        </form>
    </div>
</div>

<script src="js/app.js"></script>
</body>
</html>
