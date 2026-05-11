<?php
require_once 'db.php';
require_once 'auth.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']) ? true : false;

    $user = null;
    if ($stmt = mysqli_prepare($conn, "SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1")) {
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            $user = mysqli_fetch_assoc($res);
        }
        mysqli_stmt_close($stmt);
    }

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['name'];

        // Handle "Remember Me" - Set cookie for 30 days
        if ($remember_me) {
            setcookie('remember_user_id', $user['id'], time() + (30 * 24 * 60 * 60), '/');
            setcookie('remember_user_email', $user['email'], time() + (30 * 24 * 60 * 60), '/');
        }

        // Redirect to dashboard
        header('Location: dashboard.php');
        exit();
    } else {
        $error = 'Invalid email or password.';
    }
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Alpha Fitness</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        body {
            margin: 0;
            padding: 0;
        }
    </style>
</head>

<body>
    <div class="login-page">
        <div class="login-container">
            <!-- Hero Section -->
            <div class="login-hero">
                <div class="login-hero-content">
                    <span class="gym-icon">💪</span>
                    <h1>Alpha Fitness</h1>
                    <p>Your Fitness Journey Starts Here</p>
                    <p class="tagline">Join thousands of members achieving their fitness goals</p>
                </div>
            </div>

            <!-- Form Section -->
            <div class="login-form-section">
                <h2>Welcome Back</h2>
                <p class="form-subtitle">Sign in to your account</p>

                <?php if ($error): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="login.php">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required placeholder="Enter your email">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required placeholder="Enter your password">
                    </div>

                    <div class="link-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="remember_me" name="remember_me">
                            <label for="remember_me">Remember me</label>
                        </div>
                        <a href="forgot-password.php">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn-primary">Sign In</button>
                </form>

                <div class="signup-prompt">
                    <p>Don't have an account yet?</p>
                    <a href="register.php">Create your Alpha Fitness account today →</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>