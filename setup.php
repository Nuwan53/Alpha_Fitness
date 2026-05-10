<?php
// Database setup script
$host = 'localhost';
$user = 'root';
$pass = '';

// Connect to MySQL without database
$conn = mysqli_connect($host, $user, $pass);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create database
$createDb = "CREATE DATABASE IF NOT EXISTS gym_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!mysqli_query($conn, $createDb)) {
    die("Error creating database: " . mysqli_error($conn));
}

// Select database
if (!mysqli_select_db($conn, 'gym_db')) {
    die("Error selecting database: " . mysqli_error($conn));
}

// SQL Schema
$sql = "
-- 1. USERS (admin / trainer / member)
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    phone       VARCHAR(20),
    role        ENUM('admin','trainer','member') NOT NULL DEFAULT 'member',
    status      ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    profile_img VARCHAR(255) DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. MEMBERSHIP PLANS
CREATE TABLE IF NOT EXISTS membership_plans (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    duration_days INT NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    description TEXT,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. MEMBER PLANS
CREATE TABLE IF NOT EXISTS member_plans (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    plan_id     INT NOT NULL,
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    status      ENUM('active','expired','cancelled') DEFAULT 'active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES membership_plans(id)
);

-- 4. PAYMENTS
CREATE TABLE IF NOT EXISTS payments (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    member_plan_id INT NOT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    method         ENUM('cash','card','online') DEFAULT 'cash',
    status         ENUM('paid','pending','failed') DEFAULT 'paid',
    paid_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes          TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (member_plan_id) REFERENCES member_plans(id)
);

-- 5. TRAINERS
CREATE TABLE IF NOT EXISTS trainers (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL UNIQUE,
    specialty   VARCHAR(150),
    bio         TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. CLASSES
CREATE TABLE IF NOT EXISTS classes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    trainer_id  INT NOT NULL,
    title       VARCHAR(150) NOT NULL,
    description TEXT,
    class_date  DATE NOT NULL,
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL,
    capacity    INT DEFAULT 20,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trainer_id) REFERENCES trainers(id)
);

-- 7. CLASS BOOKINGS
CREATE TABLE IF NOT EXISTS class_bookings (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    class_id   INT NOT NULL,
    user_id    INT NOT NULL,
    booked_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status     ENUM('booked','cancelled') DEFAULT 'booked',
    UNIQUE(class_id, user_id),
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)   ON DELETE CASCADE
);

-- 8. ATTENDANCE
CREATE TABLE IF NOT EXISTS attendance (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    checked_in   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_out  TIMESTAMP NULL,
    marked_by    INT DEFAULT NULL,
    FOREIGN KEY (user_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
);
";

// Execute each SQL statement
$queries = array_filter(array_map('trim', explode(';', $sql)), function($q) { return !empty($q); });

foreach ($queries as $query) {
    if (!mysqli_query($conn, $query)) {
        echo "Error executing query: " . mysqli_error($conn) . "<br>";
    }
}

// Check if admin already exists
$adminCheck = mysqli_query($conn, "SELECT id FROM users WHERE email = 'admin@gym.com' LIMIT 1");
$adminExists = mysqli_num_rows($adminCheck) > 0;

if (!$adminExists) {
    // Insert default admin account
    // Password: admin123 (hashed with bcrypt)
    $hashedPassword = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    $insertAdmin = "INSERT INTO users (name, email, password, role) VALUES ('Gym Admin', 'admin@gym.com', '$hashedPassword', 'admin')";
    
    if (!mysqli_query($conn, $insertAdmin)) {
        echo "Error inserting admin: " . mysqli_error($conn) . "<br>";
    } else {
        echo "Admin user created successfully!<br>";
    }
}

// Check if membership plans exist
$plansCheck = mysqli_query($conn, "SELECT COUNT(*) as count FROM membership_plans");
$plansResult = mysqli_fetch_assoc($plansCheck);

if ($plansResult['count'] == 0) {
    // Insert sample membership plans
    $insertPlans = "INSERT INTO membership_plans (name, duration_days, price, description) VALUES
        ('Monthly Basic',  30,  2500.00, 'Gym access only'),
        ('Quarterly Plus', 90,  6500.00, 'Gym + 1 class/week'),
        ('Yearly Premium', 365, 22000.00, 'Unlimited access + classes')";
    
    if (!mysqli_query($conn, $insertPlans)) {
        echo "Error inserting plans: " . mysqli_error($conn) . "<br>";
    } else {
        echo "Membership plans created successfully!<br>";
    }
}

echo "Database setup completed successfully!<br>";
echo "Admin Email: admin@gym.com<br>";
echo "Admin Password: admin123<br>";

mysqli_close($conn);
?>
