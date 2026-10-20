<?php
require_once __DIR__ . '/config/db.php';

$pageTitle = "Register";
$activePage = "register";
$errorMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = get_db();
    $firstName       = trim((string) ($_POST['first_name'] ?? ''));
    $lastName        = trim((string) ($_POST['last_name'] ?? ''));
    $email           = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone           = trim((string) ($_POST['phone'] ?? ''));
    $password        = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $offers          = !empty($_POST['offers']) ? 1 : 0;

    if ($firstName === '' || $lastName === '' || $email === '' || $phone === '') {
        $errorMsg = "Please complete all required fields.";
    } elseif ($password !== $confirmPassword) {
        $errorMsg = "Passwords do not match.";
    } elseif (strlen($password) < 8 || !preg_match('/\d/', $password)) {
        $errorMsg = "Password must be at least 8 characters and include a number.";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $errorMsg = "An account with this email already exists.";
        } else {
            $code = generate_next_customer_code($pdo);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    customer_code, first_name, last_name, email, phone, password_hash,
                    role, pref_offers, member_since
                ) VALUES (?, ?, ?, ?, ?, ?, 'customer', ?, ?)
            ");
            $stmt->execute([$code, $firstName, $lastName, $email, $phone, $hash, $offers, date('F Y')]);
            unset($_SESSION['logged_out']);
            $_SESSION['user_id'] = (int) $pdo->lastInsertId();
            $_SESSION['role'] = 'customer';
            header('Location: index.php');
            exit;
        }
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
    </head>
    <body>
        <div class="website">

            <header class="navbar">
                <a href="login.php" class="logo">Laundrables</a>

                <div class="nav-buttons">
                    <a href="login.php" class="login-link">Log in</a>
                    <a href="register.php" class="btn">Get started</a>
                </div>
            </header>

            <main class="main-content">
                <section class="promo-panel">
                    <p class="eyebrow light">✧ &nbsp; LESS LAUNDRY. MORE YOU.</p>
                    <h1>A fresh start, every time.</h1>
                    <p class="promo-description">Your favorites deserve a little care. You deserve a little more time.</p>

                    <img src="img/promo.jpg" alt="Fresh laundry" class="laundry-image">

                    <blockquote>“My weekends are mine again. Everything comes back beautifully folded.”</blockquote>
                    <p class="testimonial-author">Olivarez Homes</p>

                    <div class="slider-dots">
                        <span class="active"></span>
                        <span></span>
                        <span></span>
                    </div>
                </section>

                <section class="form-section">
                    <p class="eyebrow">YOUR EVERYDAY, A LITTLE LIGHTER</p>
                    <h2>Join the neighborhood.</h2>
                    <p class="form-description">
                        Create your free account. No subscription.
                    </p>

                    <div id="register-alert" class="alert-banner <?= $errorMsg ? 'show error' : '' ?>">
                        <?= htmlspecialchars($errorMsg) ?>
                    </div>

                    <form id="register-form" action="register.php" method="post">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first-name">First name *</label>
                                <input type="text" id="first-name" name="first_name" placeholder="Troy" required>
                            </div>

                            <div class="form-group">
                                <label for="last-name">Last name *</label>
                                <input type="text" id="last-name" name="last_name" placeholder="Pogi" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email address *</label>
                            <input type="email" id="email" name="email" placeholder="troypogi@example.com" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone number *</label>
                            <input type="tel" id="phone" name="phone" placeholder="(+63)" required>
                            <small>We'll only use this for your order and pickup updates.</small>
                        </div>

                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" placeholder="Enter your password" minlength="8" required>
                            <small>Use at least 8 characters, including a number.</small>
                        </div>

                        <div class="form-group">
                            <label for="confirm-password">Confirm password *</label>
                            <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm your password" minlength="8" required>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">I agree to the Terms of Service and Privacy Policy.</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="offers" name="offers">
                            <label for="offers">Send me occasional news and neighborhood offers.</label>
                        </div>

                        <button type="submit" class="btn submit-btn">
                            Create my account →
                        </button>
                    </form>

                    <p class="account-text">
                        Already a neighbor?
                        <a href="login.php">Log in</a>
                    </p>
                </section>
            </main>
            <footer class="footer"><p>© 2026 Laundrables · Made for your everyday</p></footer>
        </div>
        <script src="js/app.js"></script>
    </body>
</html>
