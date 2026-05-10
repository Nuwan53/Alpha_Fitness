<?php
require_once 'db.php';

// Test password verification
$testPassword = 'admin123';

$result = mysqli_query($conn, 'SELECT id, name, email, password, role FROM users WHERE email = "admin@gym.com"');
$user = mysqli_fetch_assoc($result);

if ($user) {
    echo 'User found: ' . $user['name'] . PHP_EOL;
    echo 'Email: ' . $user['email'] . PHP_EOL;
    echo 'Stored hash: ' . $user['password'] . PHP_EOL;
    echo 'Test password: ' . $testPassword . PHP_EOL;
    
    if (password_verify($testPassword, $user['password'])) {
        echo 'Password verification: SUCCESS' . PHP_EOL;
    } else {
        echo 'Password verification: FAILED' . PHP_EOL;
    }
} else {
    echo 'User not found!' . PHP_EOL;
}

// List all users
echo "\nAll users in database:\n";
$allUsers = mysqli_query($conn, 'SELECT id, name, email, role FROM users');
while ($u = mysqli_fetch_assoc($allUsers)) {
    echo "- " . $u['name'] . " (" . $u['email'] . ") - Role: " . $u['role'] . PHP_EOL;
}
?>
