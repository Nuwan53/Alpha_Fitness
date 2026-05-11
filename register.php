<?php
require_once 'db.php';
require_once 'auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check if email already exists
        $checkEmail = "SELECT id FROM users WHERE email = ?";
        if ($stmt = mysqli_prepare($conn, $checkEmail)) {
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            
            if (mysqli_num_rows($res) > 0) {
                $error = 'Email already registered.';
            } else {
                // Hash password and insert user
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $insertUser = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'member')";
                
                if ($stmtInsert = mysqli_prepare($conn, $insertUser)) {
                    mysqli_stmt_bind_param($stmtInsert, 'sss', $name, $email, $hashedPassword);
                    
                    if (mysqli_stmt_execute($stmtInsert)) {
                        $success = 'Registration successful! You can now log in.';
                        $name = $email = $password = $confirm = '';
                    } else {
                        $error = 'Error during registration. Please try again.';
                    }
                    mysqli_stmt_close($stmtInsert);
                } else {
                    $error = 'Database error.';
                }
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Alpha Fitness</title>
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
                    <p>Join Our Community</p>
                    <p class="tagline">Start your fitness transformation today</p>
                </div>
            </div>

            <!-- Form Section -->
            <div class="login-form-section">
                <h2>Create Account</h2>
                <p class="form-subtitle">Join Alpha Fitness</p>

                <?php if ($error): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div style="background: #d4edda; border-left: 4px solid #27ae60; color: #155724; padding: 15px 20px; border-radius: 4px; margin-bottom: 20px; font-weight: 500;">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="register.php">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($name ?? '') ?>" required placeholder="Enter your full name">
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required placeholder="Enter your email">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required placeholder="Create a strong password">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password">
                    </div>

                    <button type="submit" class="btn-primary">Create Account</button>
                </form>

                <div class="signup-prompt">
                    <p>Already have an account?</p>
                    <a href="login.php">Sign in to your account →</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
