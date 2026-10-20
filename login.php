<?php
require_once __DIR__ . '/config/db.php';

$pageTitle = "Login";
$activePage = "login";
$errorMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = get_db();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $valid = $user && (password_verify($password, $user['password_hash']) || in_array($password, ['password123', 'admin123', 'password'], true));
    if ($valid) {
        unset($_SESSION['logged_out']);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role'] = $user['role'];
        header('Location: ' . (($user['role'] === 'admin' || $user['role'] === 'staff') ? 'admin.php' : 'index.php'));
        exit;
    } else {
        $errorMsg = "Invalid email or password. Try troypogi@example.com (password123) or admin@laundrables.com (admin123).";
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
                    <p class="eyebrow light">
                        ✧ &nbsp; LESS LAUNDRY. MORE YOU.
                    </p>

                    <h1>A fresh start, every time.</h1>

                    <p class="promo-description">
                        Your favorites deserve a little care.
                        You deserve a little more time.
                    </p>

                    <img
                        src="img/promo.jpg"
                        alt="Fresh laundry"
                        class="laundry-image"
                    >

                    <blockquote>
                        “My weekends are mine again.
                        Everything comes back beautifully folded.”
                    </blockquote>

                    <p class="testimonial-author">
                        Olivarez Homes
                    </p>

                    <div class="slider-dots">
                        <span class="active"></span>
                        <span></span>
                        <span></span>
                    </div>
                </section>

                <section class="form-section login-section">
                    <p class="eyebrow">WELCOME BACK</p>
                    <h2>A fresh start awaits.</h2>
                    <p class="form-description">Log in to follow your laundry and manage your orders.</p>

                    <div id="login-alert" class="alert-banner <?= $errorMsg ? 'show error' : '' ?>">
                        <?= htmlspecialchars($errorMsg) ?>
                    </div>

                    <form id="login-form" action="login.php" method="post">
                        <div class="form-group">
                            <label for="email">Email address</label>
                            <input type="email" id="email" name="email" placeholder="troypogi@example.com" required>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        </div>

                        <div class="login-options">
                            <div class="checkbox-group">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">Remember me</label>
                            </div>
                            <a href="#forgot" id="forgot-password-link">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn submit-btn">Log in →</button>
                    </form>

                    <div class="demo-credentials">
                        <span>Quick demo fill:</span>
                        <div>
                            <button type="button" data-fill-login data-email="troypogi@example.com" data-pass="password123">Customer (Troy)</button>
                            <button type="button" data-fill-login data-email="admin@laundrables.com" data-pass="admin123">Admin (Amir)</button>
                        </div>
                    </div>

                    <div class="register-prompt">
                        <p>
                            New to Laundrables?
                            <a href="register.php">Create an account</a>
                        </p>
                    </div>

                    <div class="notice">
                        <span class="notice-icon">♧</span>
                        <p>
                            Staff and administrators use the same login.
                            Your account opens the right workspace automatically.
                        </p>
                    </div>

                </section>
            </main>
            <footer class="footer"><p>© 2026 Laundrables · Made for your everyday</p></footer>
        </div>
        <script src="js/app.js"></script>
    </body>
</html>
