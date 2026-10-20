<?php
require_once __DIR__ . '/config/db.php';

$pageTitle = "Profile";
$activePage = "profile";

$pdo = get_db();
$user = get_current_user_record($pdo, 'customer');
$fullName = trim($user['first_name'] . ' ' . $user['last_name']);
$initials = get_user_initials($user['first_name'], $user['last_name']);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Laundrables | <?= htmlspecialchars($pageTitle) ?></title>
        <link rel="stylesheet" href="css/style.css?v=3">

        <style>
            /* Profile page layout */
            .profile-grid {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 310px;
                gap: 22px;
                align-items: start;
            }

            .profile-left,
            .profile-right {
                display: grid;
                gap: 20px;
                min-width: 0;
            }

            .profile-panel,
            .profile-card {
                background: #fff;
                border: 1px solid var(--border);
                border-radius: 15px;
                padding: 25px;
                min-width: 0;
            }

            .profile-panel h2,
            .profile-card h2 {
                font-size: 20px;
                margin: 0 0 12px;
            }

            .panel-description {
                color: var(--muted);
                font-size: 14px;
                margin-bottom: 22px;
            }

            /* Form fields */
            .profile-form-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 18px;
            }

            .profile-field {
                min-width: 0;
            }

            .profile-field.full-width {
                grid-column: 1 / -1;
            }

            .profile-field label {
                display: block;
                font-size: 13px;
                font-weight: 600;
                margin-bottom: 9px;
            }

            .profile-field input {
                display: block;
                width: 100%;
                min-width: 0;
                height: 44px;
                padding: 0 13px;
                border: 1px solid var(--border);
                border-radius: 8px;
                background: white;
                color: var(--text);
                font: inherit;
                font-size: 14px;
                outline: none;
            }

            .profile-field input:focus {
                border-color: var(--green);
                box-shadow: 0 0 0 3px rgba(18, 101, 96, 0.10);
            }

            .profile-actions {
                margin-top: 20px;
            }

            .primary-button,
            .outline-button {
                display: inline-block;
                border-radius: 8px;
                padding: 13px 17px;
                font-family: inherit;
                font-size: 13px;
                font-weight: 600;
                text-decoration: none;
                cursor: pointer;
            }

            .primary-button {
                background: var(--green);
                color: white;
                border: 1px solid var(--green);
            }

            .primary-button:hover {
                background: #0e514d;
            }

            .outline-button {
                background: white;
                color: var(--text);
                border: 1px solid var(--border);
            }

            .outline-button:hover {
                background: var(--background);
            }

            /* Customer information card */
            .avatar {
                width: 62px;
                height: 62px;
                display: flex;
                justify-content: center;
                align-items: center;
                border-radius: 50%;
                background: var(--mint);
                color: var(--green);
                font-size: 21px;
                font-weight: 700;
                margin-bottom: 17px;
                overflow: hidden;
            }

            .customer-name {
                font-size: 25px !important;
                margin-bottom: 12px !important;
                overflow-wrap: anywhere;
            }

            .customer-badge {
                display: inline-block;
                background: #f2f6f4;
                color: #718987;
                padding: 6px 10px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 600;
            }

            .customer-detail {
                margin-top: 21px;
            }

            .customer-detail span,
            .customer-detail strong {
                display: block;
            }

            .customer-detail span {
                color: var(--muted);
                font-size: 13px;
                margin-bottom: 6px;
            }

            .customer-detail strong {
                font-size: 15px;
                overflow-wrap: anywhere;
            }

            .photo-button {
                margin-top: 20px;
            }

            /* Laundry preferences */
            .preference-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
                padding: 17px 0;
                border-bottom: 1px solid var(--border);
            }

            .preference-row:first-of-type {
                padding-top: 8px;
            }

            .preference-row:last-of-type {
                border-bottom: none;
            }

            .preference-copy {
                min-width: 0;
            }

            .preference-copy strong {
                display: block;
                font-size: 14px;
                margin-bottom: 6px;
            }

            .preference-copy p {
                margin: 0;
                font-size: 12px;
                line-height: 1.5;
            }

            /* Accessible toggle switches */
            .switch {
                flex-shrink: 0;
                position: relative;
                display: inline-block;
                width: 38px;
                height: 21px;
            }

            .switch input {
                position: absolute;
                opacity: 0;
                width: 1px;
                height: 1px;
            }

            .switch-slider {
                position: absolute;
                inset: 0;
                background: #dfe9e6;
                border-radius: 30px;
                cursor: pointer;
                transition: background 0.2s;
            }

            .switch-slider::before {
                content: "";
                position: absolute;
                width: 15px;
                height: 15px;
                left: 3px;
                top: 3px;
                border-radius: 50%;
                background: white;
                transition: transform 0.2s;
            }

            .switch input:checked + .switch-slider {
                background: var(--green);
            }

            .switch input:checked + .switch-slider::before {
                transform: translateX(17px);
            }

            .switch input:focus-visible + .switch-slider {
                outline: 3px solid #8ccac0;
                outline-offset: 3px;
            }

            .password-hint {
                color: var(--muted);
                font-size: 12px;
                line-height: 1.6;
                margin-top: 20px;
            }

            .form-message {
                display: none;
                margin-top: 15px;
                padding: 12px;
                background: var(--mint);
                color: var(--green-dark);
                border-radius: 8px;
                font-size: 13px;
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
                    <a class="active" href="profile.php">› Profile</a>
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
                            <h1>Your profile</h1>
                            <p>Keep your details and laundry preferences up to date.</p>
                        </div>
                    </section>

                    <div class="profile-grid">

                        <div class="profile-left">
                            <section class="profile-panel">
                                <h2>Personal &amp; contact information</h2>

                                <p class="panel-description">
                                    These details help us get your order updates to the right place.
                                </p>

                                <form id="profile-form">
                                    <div class="profile-form-grid">

                                        <div class="profile-field">
                                            <label for="first-name">First name</label>
                                            <input id="first-name" name="first_name" type="text" value="<?= htmlspecialchars($user['first_name']) ?>" required>
                                        </div>

                                        <div class="profile-field">
                                            <label for="last-name">Last name</label>
                                            <input id="last-name" name="last_name" type="text" value="<?= htmlspecialchars($user['last_name']) ?>" required>
                                        </div>

                                        <div class="profile-field full-width">
                                            <label for="email">Email address</label>
                                            <input id="email" name="email" type="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                        </div>

                                        <div class="profile-field full-width">
                                            <label for="phone">Phone number</label>
                                            <input id="phone" name="phone" type="tel" value="<?= htmlspecialchars($user['phone']) ?>" required>
                                        </div>

                                        <div class="profile-field full-width">
                                            <label for="address">Address (optional)</label>
                                            <input id="address" name="address" type="text" value="<?= htmlspecialchars((string) $user['address']) ?>">
                                        </div>

                                        <div class="profile-field">
                                            <label for="city">City</label>
                                            <input id="city" name="city" type="text" value="<?= htmlspecialchars((string) $user['city']) ?>">
                                        </div>

                                        <div class="profile-field">
                                            <label for="zip">ZIP code</label>
                                            <input id="zip" name="zip_code" type="text" value="<?= htmlspecialchars((string) $user['zip_code']) ?>">
                                        </div>

                                    </div>

                                    <div class="profile-actions">
                                        <button class="primary-button" type="submit">Save profile changes</button>
                                    </div>

                                    <div class="form-message" id="profile-message" role="status"></div>
                                </form>
                            </section>

                            <section class="profile-panel">
                                <h2>Change password</h2>

                                <form id="password-form">
                                    <div class="profile-form-grid">
                                        <div class="profile-field full-width">
                                            <label for="current-password">Current password</label>
                                            <input id="current-password" name="current_password" type="password"
                                                placeholder="Enter current password" autocomplete="current-password" required>
                                        </div>

                                        <div class="profile-field">
                                            <label for="new-password">New password</label>
                                            <input id="new-password" name="new_password" type="password"
                                                placeholder="Enter a new password" autocomplete="new-password" minlength="8" required>
                                        </div>

                                        <div class="profile-field">
                                            <label for="confirm-password">
                                                Confirm new password
                                            </label>
                                            <input id="confirm-password" name="confirm_password" type="password"
                                                placeholder="Re-enter new password" autocomplete="new-password" minlength="8" required>
                                        </div>
                                    </div>

                                    <p class="password-hint">
                                        Use at least 8 characters, including a number.
                                        Don’t reuse a password from another account.
                                    </p>

                                    <div class="profile-actions">
                                        <button class="outline-button" type="submit">
                                            Update password
                                        </button>
                                    </div>

                                    <div class="form-message" id="password-message"
                                        role="status"></div>
                                </form>
                            </section>
                        </div>

                        <div class="profile-right">
                            <!-- Customer card -->
                            <section class="profile-card">
                                <div class="avatar" id="avatar">
                                    <?php if (!empty($user['avatar_data'])): ?>
                                        <img src="<?= htmlspecialchars($user['avatar_data']) ?>" alt="Customer photo">
                                    <?php else: ?>
                                        <?= htmlspecialchars($initials) ?>
                                    <?php endif; ?>
                                </div>

                                <h2 class="customer-name" id="customer-name"><?= htmlspecialchars($fullName) ?></h2>

                                <span class="customer-badge"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>

                                <div class="customer-detail">
                                    <span>Neighbor since</span>
                                    <strong><?= htmlspecialchars($user['member_since']) ?></strong>
                                </div>

                                <div class="customer-detail">
                                    <span>Customer ID</span>
                                    <strong><?= htmlspecialchars($user['customer_code']) ?></strong>
                                </div>

                                <label for="photo-upload" class="outline-button photo-button">
                                    Change photo
                                </label>

                                <!-- Automatic photo preview & upload to database -->
                                <input type="file" id="photo-upload" accept="image/*" hidden>
                            </section>

                            <section class="profile-card">
                                <h2>Laundry preferences</h2>

                                <form id="preferences-form">

                                    <div class="preference-row">
                                        <div class="preference-copy">
                                            <strong>Fragrance-free detergent</strong>
                                            <p>Applied to new orders automatically.</p>
                                        </div>

                                        <label class="switch">
                                            <input type="checkbox"
                                                name="fragrance_free" <?= !empty($user['pref_fragrance_free']) ? 'checked' : '' ?>>
                                            <span class="switch-slider"></span>
                                        </label>
                                    </div>

                                    <div class="preference-row">
                                        <div class="preference-copy">
                                            <strong>Low-heat drying</strong>
                                            <p>A gentler finish for everyday fabrics.</p>
                                        </div>

                                        <label class="switch">
                                            <input type="checkbox"
                                                name="low_heat" <?= !empty($user['pref_low_heat']) ? 'checked' : '' ?>>
                                            <span class="switch-slider"></span>
                                        </label>
                                    </div>

                                    <div class="preference-row">
                                        <div class="preference-copy">
                                            <strong>Pickup SMS alerts</strong>
                                            <p>Get a message when your order is ready.</p>
                                        </div>

                                        <label class="switch">
                                            <input type="checkbox"
                                                name="sms_alerts" <?= !empty($user['pref_sms_alerts']) ? 'checked' : '' ?>>
                                            <span class="switch-slider"></span>
                                        </label>
                                    </div>

                                    <div class="preference-row">
                                        <div class="preference-copy">
                                            <strong>Email order updates</strong>
                                        </div>

                                        <label class="switch">
                                            <input type="checkbox"
                                                name="email_updates" <?= !empty($user['pref_email_updates']) ? 'checked' : '' ?>>
                                            <span class="switch-slider"></span>
                                        </label>
                                    </div>

                                    <div class="preference-row">
                                        <div class="preference-copy">
                                            <strong>Neighborhood offers</strong>
                                        </div>

                                        <label class="switch">
                                            <input type="checkbox"
                                                name="offers" <?= !empty($user['pref_offers']) ? 'checked' : '' ?>>
                                            <span class="switch-slider"></span>
                                        </label>
                                    </div>

                                    <button class="primary-button" type="submit">
                                        Save preferences
                                    </button>

                                    <div class="form-message" id="preferences-message"
                                        role="status"></div>
                                </form>
                            </section>
                        </div>
                    </div>
                    <footer>© 2026 Laundrables · Made for your everyday</footer>
                </div>
            </main>
        </div>
        <script src="js/app.js"></script>
    </body>
</html>
