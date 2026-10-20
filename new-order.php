<?php
require_once __DIR__ . '/config/db.php';

$pageTitle = "New order";
$activePage = "new-order";

$pdo = get_db();
$user = get_current_user_record($pdo, 'customer');
$fullName = trim($user['first_name'] . ' ' . $user['last_name']);
$isStaff = in_array($user['role'], ['admin', 'staff'], true);

// The customer's own address from their Profile page
$profileAddress = trim(implode(', ', array_filter([
    trim((string) ($user['address'] ?? '')),
    trim((string) ($user['city'] ?? '')),
    trim((string) ($user['zip_code'] ?? '')),
], fn($p) => $p !== '')));

// Current Manila date and time, so the date picker never offers a past day
$todayIso   = date('Y-m-d');
$maxIso     = date('Y-m-d', strtotime('+60 days'));
$nowMinutes = ((int) date('G')) * 60 + (int) date('i');
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Laundrables | <?= htmlspecialchars($pageTitle) ?></title>
        <link rel="stylesheet" href="css/style.css?v=3">
        <style>
            .order-grid {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 340px;
                gap: 22px;
                align-items: start;
            }

            .form-panel, .summary-panel {
                background: white;
                border: 1px solid #dfe9e6;
                border-radius: 14px;
                padding: 25px;
                margin-bottom: 20px;
            }

            .form-panel h2, .summary-panel h2 {
                font-size: 20px;
                margin-top: 0;
            }

            .form-description {
                font-size: 13px;
                color: #7a9090;
            }

            .services {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 12px;
                margin: 20px 0 25px;
            }

            .service-option {
                min-height: 120px;
                border: 1px solid #dfe9e6;
                border-radius: 12px;
                background: white;
                text-align: left;
                padding: 15px;
                cursor: pointer;
                color: #173f40;
                transition: border-color 0.15s ease, background-color 0.15s ease;
            }

            .service-option.selected {
                border: 2px solid #126560;
                background: #e7f5f0;
            }

            .service-icon {
                display: block;
                font-size: 25px;
                color: #126560;
                margin-bottom: 12px;
            }

            .service-option strong,
            .service-option small {
                display: block;
            }

            .service-option strong {
                font-size: 13px;
                margin-bottom: 9px;
            }

            .service-option small {
                color: #7a9090;
                font-size: 12px;
            }

            .field-label {
                display: block;
                font-weight: bold;
                font-size: 13px;
                margin: 18px 0 9px;
            }

            .field {
                width: 100%;
                padding: 14px;
                min-height: 46px;
                border: 1px solid #dfe9e6;
                border-radius: 8px;
                background: white;
                color: #173f40;
                font: inherit;
            }

            textarea.field {
                min-height: 100px;
                resize: vertical;
            }

            .hint {
                color: #7a9090;
                font-size: 12px;
                margin-top: 8px;
            }

            .check-option {
                display: flex;
                gap: 10px;
                align-items: center;
                margin-top: 17px;
                font-size: 13px;
            }

            .check-option input {
                width: 17px;
                height: 17px;
                accent-color: #126560;
            }

            .date-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }

            .summary-info {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin: 22px 0;
            }

            .summary-info span,
            .summary-info strong {
                display: block;
            }

            .summary-info span {
                font-size: 12px;
                color: #7a9090;
                margin-bottom: 7px;
            }

            .summary-info strong {
                font-size: 13px;
            }

            .price-row {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                border-top: 1px solid #dfe9e6;
                padding: 17px 0;
                font-size: 13px;
            }

            .price-row span {
                color: #7a9090;
            }

            .estimate-label {
                border-top: 1px solid #dfe9e6;
                padding-top: 20px;
                color: #7a9090;
                font-size: 11px;
            }

            .total-price {
                font-size: 38px;
                font-weight: bold;
                margin: 12px 0;
            }

            .summary-note {
                color: #7a9090;
                font-size: 12px;
                line-height: 1.6;
            }

            .full-button {
                width: 100%;
                text-align: center;
                cursor: pointer;
                font: inherit;
                font-size: 13px;
                font-weight: bold;
                border: 0;
                border-radius: 8px;
                padding: 14px;
                display: block;
                margin-top: 12px;
            }

            .continue-button {
                background: #126560;
                color: white;
            }

            .cancel-button {
                background: white;
                border: 1px solid #dfe9e6;
                color: #173f40;
            }

            .basket-note {
                background: #e7f5f0;
                border-radius: 13px;
                padding: 20px;
                font-size: 13px;
                line-height: 1.6;
            }

            .basket-note p {
                font-size: 12px;
                margin-bottom: 0;
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
                    <a class="active" href="new-order.php">› New Order</a>
                    <a href="profile.php">Profile</a>
                    <a href="service.php">Help &amp; Support</a>
                    <?php if ($isStaff): ?><a href="admin.php">← Back to Admin</a><?php endif; ?>
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
                    <h1>Let’s lighten your load</h1>
                    <p>Tell us what needs a fresh start. We’ll handle the rest.</p>

                    <div id="order-feedback" class="alert-banner"></div>

                    <div class="order-grid">
                        <div>
                            <section class="form-panel">
                                <h2>Choose your care</h2>
                                <p class="form-description">Select one service for this order.</p>

                                <div class="services">
                                    <button type="button" class="service-option selected"
                                        data-name="Wash & Fold" data-price="60" data-unit="kg">
                                        <span class="service-icon">▧</span>
                                        <strong>Wash &amp; Fold</strong>
                                        <small>₱60 / kg</small>
                                    </button>

                                    <button type="button" class="service-option"
                                        data-name="Dry Clean" data-price="500" data-unit="item">
                                        <span class="service-icon">♧</span>
                                        <strong>Dry Clean</strong>
                                        <small>₱500 / piece</small>
                                    </button>

                                    <button type="button" class="service-option"
                                        data-name="Special Care" data-price="300" data-unit="kg">
                                        <span class="service-icon">✧</span>
                                        <strong>Special Care</strong>
                                        <small>₱300 / kg</small>
                                    </button>
                                </div>

                                <label class="field-label" id="quantity-label" for="quantity">
                                    Estimated weight (kg) *
                                </label>
                                <input class="field" id="quantity"
                                    type="number" min="3" step="0.5" value="8.5">

                                <p class="hint" id="quantity-hint">3 kg minimum. Final weight is confirmed at drop-off.</p>

                                <label class="field-label" for="instructions">
                                    Care instructions
                                </label>
                                <textarea class="field" id="instructions">Please use fragrance-free detergent and low-heat drying. Separate the white cotton shirts.</textarea>

                                <label class="check-option">
                                    <input type="checkbox" id="pref-fragrance-free" <?= !empty($user['pref_fragrance_free']) ? 'checked' : '' ?>>
                                    Use my saved fragrance-free preference
                                </label>

                                <label class="check-option">
                                    <input type="checkbox" id="pref-low-heat" <?= !empty($user['pref_low_heat']) ? 'checked' : '' ?>>
                                    Low-heat drying
                                </label>
                            </section>

                            <section class="form-panel">
                                <h2>Your drop-off</h2>
                                <div class="date-grid">
                                    <div>
                                        <label class="field-label" for="drop-date">Drop-off date *</label>
                                        <input class="field" type="date" id="drop-date"
                                            value="<?= $todayIso ?>" min="<?= $todayIso ?>" max="<?= $maxIso ?>"
                                            data-today="<?= $todayIso ?>" data-now-minutes="<?= $nowMinutes ?>">
                                    </div>

                                    <div>
                                        <label class="field-label" for="drop-time">Preferred time *</label>
                                        <select class="field" id="drop-time">
                                            <option value="10:00 AM–12:00 PM" data-hour="10">10:00 AM–12:00 PM</option>
                                            <option value="2:00 PM–4:00 PM" data-hour="14">2:00 PM–4:00 PM</option>
                                            <option value="4:00 PM–6:00 PM" data-hour="16">4:00 PM–6:00 PM</option>
                                        </select>
                                    </div>
                                </div>
                                <p class="hint" id="drop-hint">You can book from today up to 60 days ahead. Time slots that have already started today are unavailable.</p>
                                <p class="hint">
                                    <?php if ($profileAddress !== ''): ?>
                                        Your address on file: <strong><?= htmlspecialchars($profileAddress) ?></strong>
                                        (<a href="profile.php" style="text-decoration:underline;">change in Profile</a>).
                                    <?php else: ?>
                                        No address saved yet. <a href="profile.php" style="text-decoration:underline;">Add one in Profile</a>.
                                    <?php endif; ?>
                                    Payment is due before pickup.
                                </p>
                            </section>
                        </div>

                        <aside>
                            <section class="summary-panel">
                                <h2>Your estimated total</h2>
                                <div class="summary-info">
                                    <div>
                                        <span>Service</span>
                                        <strong id="summary-service">Wash &amp; Fold</strong>
                                    </div>
                                    <div>
                                        <span>Turnaround</span>
                                        <strong id="turnaround">24 hours</strong>
                                    </div>
                                </div>

                                <div class="price-row">
                                    <span id="price-detail">8.5 kg × ₱60.00</span>
                                    <strong id="subtotal">₱510.00</strong>
                                </div>

                                <div class="price-row">
                                    <span>Care preferences</span>
                                    <span>Included</span>
                                </div>

                                <div class="estimate-label">ESTIMATED TOTAL</div>
                                <div class="total-price" id="total">₱510.00</div>

                                <p class="summary-note">Final total confirmed at drop-off. No charge until your order is accepted.</p>

                                <button class="full-button continue-button"
                                    id="continue-button" type="button">Continue to confirmation →
                                </button>

                                <a class="full-button cancel-button" href="index.php">Cancel</a>
                            </section>

                            <section class="basket-note">
                                <strong>▱ &nbsp; A basket, not a box</strong>
                                <p>Loose laundry in a reusable bag or basket works perfectly. We’ll take it from there.</p>
                            </section>
                        </aside>
                    </div>
                    <footer>© 2026 Laundrables · Made for your everyday</footer>
                </div>
            </main>
        </div>
        <script src="js/app.js"></script>
    </body>
</html>
