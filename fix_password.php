<?php
require_once 'db.php';

// Generate correct bcrypt hash for 'admin123'
$password = 'admin123';
$correctHash = password_hash($password, PASSWORD_BCRYPT);

echo 'Correct hash for "admin123": ' . $correctHash . PHP_EOL;
echo 'Verifying hash: ' . (password_verify($password, $correctHash) ? 'YES' : 'NO') . PHP_EOL;

// Update admin user with correct password
$updateQuery = "UPDATE users SET password = ? WHERE email = 'admin@gym.com'";
if ($stmt = mysqli_prepare($conn, $updateQuery)) {
    mysqli_stmt_bind_param($stmt, 's', $correctHash);
    if (mysqli_stmt_execute($stmt)) {
        echo 'Admin password updated successfully!' . PHP_EOL;
    } else {
        echo 'Error updating password: ' . mysqli_error($conn) . PHP_EOL;
    }
    mysqli_stmt_close($stmt);
}

// Verify the update
$result = mysqli_query($conn, 'SELECT password FROM users WHERE email = "admin@gym.com"');
$user = mysqli_fetch_assoc($result);
echo 'New stored hash: ' . $user['password'] . PHP_EOL;
echo 'Verify new hash: ' . (password_verify($password, $user['password']) ? 'YES' : 'NO') . PHP_EOL;
?>
