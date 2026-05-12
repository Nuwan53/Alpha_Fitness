<?php

/**
 * CRUD Helper Functions
 * Centralized functions for Create, Read, Update, Delete operations
 */

// ==========================================
// MEMBERS CRUD
// ==========================================

function addMember($conn, $name, $email, $phone, $password = null) {
    // Hash password if provided, otherwise generate a random one
    if ($password === null) {
        $password = 'Gym@' . substr(md5(time()), 0, 6);
    }
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    $query = "INSERT INTO users (name, email, phone, password, role, status) VALUES (?, ?, ?, ?, 'member', 'active')";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $phone, $hashedPassword);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return mysqli_insert_id($conn);
        }
        mysqli_stmt_close($stmt);
    }
    return false;
}

function updateMember($conn, $userId, $name, $email, $phone, $status) {
    $query = "UPDATE users SET name = ?, email = ?, phone = ?, status = ? WHERE id = ? AND role = 'member'";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $phone, $status, $userId);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

function getMemberById($conn, $userId) {
    $query = "SELECT u.*, 
              (SELECT plan_id FROM member_plans WHERE user_id = u.id AND status = 'active' LIMIT 1) as active_plan_id,
              (SELECT name FROM membership_plans WHERE id = (SELECT plan_id FROM member_plans WHERE user_id = u.id AND status = 'active' LIMIT 1)) as active_plan_name,
              (SELECT end_date FROM member_plans WHERE user_id = u.id AND status = 'active' LIMIT 1) as plan_end_date
              FROM users u 
              WHERE u.id = ? AND u.role = 'member'";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $member = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $member;
    }
    return null;
}

function deleteMember($conn, $userId) {
    // Cascade delete: attendance, class_bookings, member_plans, payments, then user
    $queries = [
        "DELETE FROM attendance WHERE user_id = $userId",
        "DELETE FROM class_bookings WHERE user_id = $userId",
        "DELETE FROM payments WHERE user_id = $userId",
        "DELETE FROM member_plans WHERE user_id = $userId",
        "DELETE FROM users WHERE id = $userId AND role = 'member'"
    ];
    
    foreach ($queries as $query) {
        if (!mysqli_query($conn, $query)) {
            return false;
        }
    }
    return true;
}

// ==========================================
// TRAINERS CRUD
// ==========================================

function addTrainer($conn, $userId, $specialty, $bio) {
    $query = "INSERT INTO trainers (user_id, specialty, bio) VALUES (?, ?, ?)";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'iss', $userId, $specialty, $bio);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return mysqli_insert_id($conn);
        }
        mysqli_stmt_close($stmt);
    }
    return false;
}

function updateTrainer($conn, $trainerId, $specialty, $bio) {
    $query = "UPDATE trainers SET specialty = ?, bio = ? WHERE id = ?";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'ssi', $specialty, $bio, $trainerId);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

function getTrainerById($conn, $trainerId) {
    $query = "SELECT t.*, u.name, u.email, u.phone, u.status 
              FROM trainers t
              JOIN users u ON t.user_id = u.id
              WHERE t.id = ?";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'i', $trainerId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $trainer = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $trainer;
    }
    return null;
}

function getAllTrainers($conn) {
    $query = "SELECT t.*, u.name, u.email, u.phone, u.status,
              COUNT(c.id) as total_classes,
              (SELECT COUNT(*) FROM class_bookings cb JOIN classes c ON cb.class_id = c.id WHERE c.trainer_id = t.id) as total_bookings
              FROM trainers t
              JOIN users u ON t.user_id = u.id
              LEFT JOIN classes c ON t.id = c.trainer_id
              GROUP BY t.id
              ORDER BY u.name ASC";
    
    $result = mysqli_query($conn, $query);
    $trainers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $trainers[] = $row;
    }
    return $trainers;
}

function deleteTrainer($conn, $trainerId) {
    // Check if trainer has classes
    $checkQuery = "SELECT COUNT(*) as count FROM classes WHERE trainer_id = ?";
    if ($stmt = mysqli_prepare($conn, $checkQuery)) {
        mysqli_stmt_bind_param($stmt, 'i', $trainerId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($row['count'] > 0) {
            return 'Cannot delete trainer with scheduled classes';
        }
    }
    
    // Get user_id and delete trainer + user
    $getQuery = "SELECT user_id FROM trainers WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $getQuery)) {
        mysqli_stmt_bind_param($stmt, 'i', $trainerId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        $userId = $row['user_id'];
        
        // Delete trainer and user
        $deleteTrainerQuery = "DELETE FROM trainers WHERE id = ?";
        $deleteUserQuery = "DELETE FROM users WHERE id = ?";
        
        if (mysqli_query($conn, $deleteTrainerQuery) && mysqli_query($conn, $deleteUserQuery)) {
            return true;
        }
    }
    return false;
}

// ==========================================
// MEMBERSHIP PLANS CRUD
// ==========================================

function addPlan($conn, $name, $duration_days, $price, $description) {
    $query = "INSERT INTO membership_plans (name, duration_days, price, description, is_active) VALUES (?, ?, ?, ?, 1)";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'sids', $name, $duration_days, $price, $description);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return mysqli_insert_id($conn);
        }
        mysqli_stmt_close($stmt);
    }
    return false;
}

function updatePlan($conn, $planId, $name, $duration_days, $price, $description, $is_active) {
    $query = "UPDATE membership_plans SET name = ?, duration_days = ?, price = ?, description = ?, is_active = ? WHERE id = ?";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'sidisi', $name, $duration_days, $price, $description, $is_active, $planId);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

function getPlanById($conn, $planId) {
    $query = "SELECT * FROM membership_plans WHERE id = ?";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'i', $planId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $plan = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $plan;
    }
    return null;
}

function getAllPlans($conn, $activeOnly = false) {
    $query = "SELECT p.*,
              COUNT(mp.id) as active_members
              FROM membership_plans p
              LEFT JOIN member_plans mp ON p.id = mp.plan_id AND mp.status = 'active'
              " . ($activeOnly ? "WHERE p.is_active = 1" : "") . "
              GROUP BY p.id
              ORDER BY p.price ASC";
    
    $result = mysqli_query($conn, $query);
    $plans = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $plans[] = $row;
    }
    return $plans;
}

function deletePlan($conn, $planId) {
    // Check if plan has active members
    $checkQuery = "SELECT COUNT(*) as count FROM member_plans WHERE plan_id = ? AND status = 'active'";
    if ($stmt = mysqli_prepare($conn, $checkQuery)) {
        mysqli_stmt_bind_param($stmt, 'i', $planId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        if ($row['count'] > 0) {
            return 'Cannot delete plan with active members. Mark as inactive instead.';
        }
    }
    
    $query = "DELETE FROM membership_plans WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'i', $planId);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

// ==========================================
// CLASSES CRUD
// ==========================================

function addClass($conn, $trainer_id, $title, $description, $class_date, $start_time, $end_time, $capacity) {
    $query = "INSERT INTO classes (trainer_id, title, description, class_date, start_time, end_time, capacity) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'isssssi', $trainer_id, $title, $description, $class_date, $start_time, $end_time, $capacity);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return mysqli_insert_id($conn);
        }
        mysqli_stmt_close($stmt);
    }
    return false;
}

function updateClass($conn, $classId, $trainer_id, $title, $description, $class_date, $start_time, $end_time, $capacity) {
    $query = "UPDATE classes SET trainer_id = ?, title = ?, description = ?, class_date = ?, start_time = ?, end_time = ?, capacity = ? 
              WHERE id = ?";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'isssssii', $trainer_id, $title, $description, $class_date, $start_time, $end_time, $capacity, $classId);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

function getClassById($conn, $classId) {
    $query = "SELECT c.*, t.id as trainer_id, u.name as trainer_name,
              COUNT(cb.id) as booked_count
              FROM classes c
              LEFT JOIN trainers t ON c.trainer_id = t.id
              LEFT JOIN users u ON t.user_id = u.id
              LEFT JOIN class_bookings cb ON c.id = cb.class_id AND cb.status = 'booked'
              WHERE c.id = ?
              GROUP BY c.id";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'i', $classId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $class = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $class;
    }
    return null;
}

function getAllClasses($conn, $limit = 20, $offset = 0) {
    $query = "SELECT c.*, u.name as trainer_name,
              COUNT(cb.id) as booked_count
              FROM classes c
              LEFT JOIN trainers t ON c.trainer_id = t.id
              LEFT JOIN users u ON t.user_id = u.id
              LEFT JOIN class_bookings cb ON c.id = cb.class_id AND cb.status = 'booked'
              GROUP BY c.id
              ORDER BY c.class_date DESC, c.start_time ASC
              LIMIT ? OFFSET ?";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $classes = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $classes[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $classes;
    }
    return [];
}

function getClassesCount($conn) {
    $query = "SELECT COUNT(*) as count FROM classes";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

function deleteClass($conn, $classId) {
    // First delete all bookings for this class
    $deleteBookingsQuery = "DELETE FROM class_bookings WHERE class_id = ?";
    if ($stmt = mysqli_prepare($conn, $deleteBookingsQuery)) {
        mysqli_stmt_bind_param($stmt, 'i', $classId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    
    // Then delete the class
    $query = "DELETE FROM classes WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'i', $classId);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

function getClassBookings($conn, $classId) {
    $query = "SELECT u.id, u.name, u.email, cb.booked_at, cb.status
              FROM class_bookings cb
              JOIN users u ON cb.user_id = u.id
              WHERE cb.class_id = ? AND cb.status = 'booked'
              ORDER BY cb.booked_at ASC";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'i', $classId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $bookings = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $bookings[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $bookings;
    }
    return [];
}

// ==========================================
// PAYMENTS CRUD
// ==========================================

function recordPayment($conn, $userId, $memberPlanId, $amount, $method, $status, $notes = '') {
    $query = "INSERT INTO payments (user_id, member_plan_id, amount, method, status, notes) 
              VALUES (?, ?, ?, ?, ?, ?)";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'iidss', $userId, $memberPlanId, $amount, $method, $status, $notes);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return mysqli_insert_id($conn);
        }
        mysqli_stmt_close($stmt);
    }
    return false;
}

function getPaymentById($conn, $paymentId) {
    $query = "SELECT p.*, u.name, mp.start_date, mp.end_date, pl.name as plan_name
              FROM payments p
              JOIN users u ON p.user_id = u.id
              JOIN member_plans mp ON p.member_plan_id = mp.id
              JOIN membership_plans pl ON mp.plan_id = pl.id
              WHERE p.id = ?";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'i', $paymentId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $payment = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $payment;
    }
    return null;
}

function getAllPayments($conn, $limit = 50, $offset = 0, $filters = []) {
    $query = "SELECT p.id, p.amount, p.method, p.status, p.paid_at, u.name, pl.name as plan_name
              FROM payments p
              JOIN users u ON p.user_id = u.id
              JOIN member_plans mp ON p.member_plan_id = mp.id
              JOIN membership_plans pl ON mp.plan_id = pl.id
              WHERE 1=1";
    
    if (!empty($filters['start_date'])) {
        $query .= " AND DATE(p.paid_at) >= '" . mysqli_real_escape_string($conn, $filters['start_date']) . "'";
    }
    if (!empty($filters['end_date'])) {
        $query .= " AND DATE(p.paid_at) <= '" . mysqli_real_escape_string($conn, $filters['end_date']) . "'";
    }
    if (!empty($filters['status'])) {
        $query .= " AND p.status = '" . mysqli_real_escape_string($conn, $filters['status']) . "'";
    }
    if (!empty($filters['method'])) {
        $query .= " AND p.method = '" . mysqli_real_escape_string($conn, $filters['method']) . "'";
    }
    
    $query .= " ORDER BY p.paid_at DESC LIMIT ? OFFSET ?";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $payments = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $payments[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $payments;
    }
    return [];
}

function getPaymentsCount($conn, $filters = []) {
    $query = "SELECT COUNT(*) as count FROM payments p WHERE 1=1";
    
    if (!empty($filters['start_date'])) {
        $query .= " AND DATE(p.paid_at) >= '" . mysqli_real_escape_string($conn, $filters['start_date']) . "'";
    }
    if (!empty($filters['end_date'])) {
        $query .= " AND DATE(p.paid_at) <= '" . mysqli_real_escape_string($conn, $filters['end_date']) . "'";
    }
    if (!empty($filters['status'])) {
        $query .= " AND p.status = '" . mysqli_real_escape_string($conn, $filters['status']) . "'";
    }
    if (!empty($filters['method'])) {
        $query .= " AND p.method = '" . mysqli_real_escape_string($conn, $filters['method']) . "'";
    }
    
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}

// ==========================================
// ATTENDANCE CRUD
// ==========================================

function checkInMember($conn, $userId) {
    // Check if already checked in today
    $checkQuery = "SELECT id FROM attendance WHERE user_id = ? AND DATE(checked_in) = CURDATE() AND checked_out IS NULL";
    if ($stmt = mysqli_prepare($conn, $checkQuery)) {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($result) > 0) {
            mysqli_stmt_close($stmt);
            return 'already_checked_in';
        }
        mysqli_stmt_close($stmt);
    }
    
    $query = "INSERT INTO attendance (user_id, checked_in) VALUES (?, NOW())";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return mysqli_insert_id($conn);
        }
        mysqli_stmt_close($stmt);
    }
    return false;
}

function checkOutMember($conn, $userId) {
    $query = "UPDATE attendance SET checked_out = NOW() WHERE user_id = ? AND checked_out IS NULL AND DATE(checked_in) = CURDATE() LIMIT 1";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result ? true : false;
    }
    return false;
}

function getTodayAttendance($conn) {
    $query = "SELECT a.*, u.name, u.email,
              TIME_FORMAT(a.checked_in, '%H:%i') as check_in_time,
              TIME_FORMAT(a.checked_out, '%H:%i') as check_out_time,
              TIMEDIFF(a.checked_out, a.checked_in) as duration
              FROM attendance a
              JOIN users u ON a.user_id = u.id
              WHERE DATE(a.checked_in) = CURDATE()
              ORDER BY a.checked_in DESC";
    
    $result = mysqli_query($conn, $query);
    $attendance = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $attendance[] = $row;
    }
    return $attendance;
}

function getMemberAttendanceHistory($conn, $userId, $limit = 50) {
    $query = "SELECT a.*,
              TIME_FORMAT(a.checked_in, '%H:%i') as check_in_time,
              TIME_FORMAT(a.checked_out, '%H:%i') as check_out_time,
              TIMEDIFF(a.checked_out, a.checked_in) as duration
              FROM attendance a
              WHERE a.user_id = ?
              ORDER BY a.checked_in DESC
              LIMIT ?";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'ii', $userId, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $attendance = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $attendance[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $attendance;
    }
    return [];
}

// ==========================================
// MEMBER PLANS CRUD
// ==========================================

function assignMemberPlan($conn, $userId, $planId, $startDate) {
    $plan = getPlanById($conn, $planId);
    if (!$plan) return false;
    
    // Calculate end date
    $endDate = date('Y-m-d', strtotime($startDate . ' + ' . $plan['duration_days'] . ' days'));
    
    $query = "INSERT INTO member_plans (user_id, plan_id, start_date, end_date, status) 
              VALUES (?, ?, ?, ?, 'active')";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'iiss', $userId, $planId, $startDate, $endDate);
        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return mysqli_insert_id($conn);
        }
        mysqli_stmt_close($stmt);
    }
    return false;
}

function getMemberPlans($conn, $userId) {
    $query = "SELECT mp.*, pl.name, pl.price, pl.duration_days
              FROM member_plans mp
              JOIN membership_plans pl ON mp.plan_id = pl.id
              WHERE mp.user_id = ?
              ORDER BY mp.start_date DESC";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $plans = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $plans[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $plans;
    }
    return [];
}

function getMembersOnPlan($conn, $planId) {
    $query = "SELECT u.id, u.name, u.email, mp.start_date, mp.end_date, mp.status
              FROM member_plans mp
              JOIN users u ON mp.user_id = u.id
              WHERE mp.plan_id = ?
              ORDER BY mp.start_date DESC";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, 'i', $planId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $members = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $members[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $members;
    }
    return [];
}

?>
