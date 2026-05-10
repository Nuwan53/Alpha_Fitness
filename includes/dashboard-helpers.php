<?php

/**
 * Dashboard Helper Functions
 * All database queries and metrics calculations
 */

// ==========================================
// REVENUE METRICS
// ==========================================

function getTotalRevenue($conn, $period = 'month') {
    $query = "SELECT SUM(amount) as total FROM payments WHERE status = 'paid'";
    
    if ($period === 'month') {
        $query .= " AND YEAR(paid_at) = YEAR(CURDATE()) AND MONTH(paid_at) = MONTH(CURDATE())";
    } elseif ($period === 'year') {
        $query .= " AND YEAR(paid_at) = YEAR(CURDATE())";
    }
    
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['total'] ?? 0;
}

function getYearToDateRevenue($conn) {
    return getTotalRevenue($conn, 'year');
}

function getPaymentMethodBreakdown($conn) {
    $query = "
        SELECT 
            method, 
            COUNT(*) as count, 
            SUM(amount) as total,
            ROUND((SUM(amount) / (SELECT SUM(amount) FROM payments WHERE status = 'paid')) * 100, 2) as percentage
        FROM payments 
        WHERE status = 'paid'
        GROUP BY method
        ORDER BY total DESC
    ";
    
    $result = mysqli_query($conn, $query);
    $methods = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $methods[] = $row;
    }
    return $methods;
}

function getTopMembershipPlans($conn, $limit = 5) {
    $query = "
        SELECT 
            mp.id,
            mp.name,
            COUNT(mbp.id) as active_members,
            SUM(p.amount) as total_revenue,
            COUNT(mbp.id) as total_enrollments
        FROM membership_plans mp
        LEFT JOIN member_plans mbp ON mp.id = mbp.plan_id AND mbp.status = 'active'
        LEFT JOIN payments p ON mbp.id = p.member_plan_id AND p.status = 'paid'
        GROUP BY mp.id, mp.name
        ORDER BY total_revenue DESC
        LIMIT " . intval($limit);
    
    $result = mysqli_query($conn, $query);
    $plans = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $plans[] = $row;
    }
    return $plans;
}

function getUpcomingRenewals($conn, $days = 30) {
    $query = "
        SELECT 
            u.id,
            u.name,
            u.email,
            mp.end_date,
            pl.name as plan_name,
            pl.price,
            DATEDIFF(mp.end_date, CURDATE()) as days_until_renewal
        FROM member_plans mp
        JOIN users u ON mp.user_id = u.id
        JOIN membership_plans pl ON mp.plan_id = pl.id
        WHERE mp.status = 'active' 
        AND mp.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL " . intval($days) . " DAY)
        ORDER BY mp.end_date ASC
        LIMIT 10
    ";
    
    $result = mysqli_query($conn, $query);
    $renewals = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $renewals[] = $row;
    }
    return $renewals;
}

// ==========================================
// MEMBERSHIP METRICS
// ==========================================

function getActiveMembersCount($conn) {
    $query = "
        SELECT COUNT(DISTINCT user_id) as count 
        FROM member_plans 
        WHERE status = 'active' AND end_date >= CURDATE()
    ";
    
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

function getMembershipStats($conn) {
    $query = "
        SELECT 
            'Active' as status, COUNT(*) as count, 'active' as status_key
        FROM member_plans 
        WHERE status = 'active' AND end_date >= CURDATE()
        
        UNION ALL
        
        SELECT 
            'Expired' as status, COUNT(*) as count, 'expired' as status_key
        FROM member_plans 
        WHERE status = 'expired' OR (status = 'active' AND end_date < CURDATE())
        
        UNION ALL
        
        SELECT 
            'Cancelled' as status, COUNT(*) as count, 'cancelled' as status_key
        FROM member_plans 
        WHERE status = 'cancelled'
    ";
    
    $result = mysqli_query($conn, $query);
    $stats = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $stats[] = $row;
    }
    return $stats;
}

function getNewMembersThisMonth($conn) {
    $query = "
        SELECT COUNT(DISTINCT user_id) as count
        FROM member_plans
        WHERE YEAR(created_at) = YEAR(CURDATE()) 
        AND MONTH(created_at) = MONTH(CURDATE())
    ";
    
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

function getChurnRate($conn) {
    $query = "
        SELECT 
            ROUND(
                (SELECT COUNT(*) FROM member_plans WHERE status = 'cancelled' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()))
                /
                NULLIF((SELECT COUNT(*) FROM member_plans WHERE status = 'active' AND MONTH(created_at) < MONTH(CURDATE()) OR (MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()))), 0)
                * 100, 2
            ) as churn_rate
    ";
    
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['churn_rate'] ?? 0;
}

function getMemberList($conn, $limit = 20, $offset = 0) {
    $query = "
        SELECT 
            u.id,
            u.name,
            u.email,
            u.phone,
            u.created_at,
            mp.plan_id,
            pl.name as plan_name,
            mp.start_date,
            mp.end_date,
            mp.status as membership_status,
            DATEDIFF(mp.end_date, CURDATE()) as days_remaining
        FROM users u
        LEFT JOIN member_plans mp ON u.id = mp.user_id AND mp.status = 'active'
        LEFT JOIN membership_plans pl ON mp.plan_id = pl.id
        WHERE u.role = 'member'
        ORDER BY u.created_at DESC
        LIMIT " . intval($limit) . " OFFSET " . intval($offset);
    
    $result = mysqli_query($conn, $query);
    $members = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $members[] = $row;
    }
    return $members;
}

function getTotalMembersCount($conn) {
    $query = "SELECT COUNT(*) as count FROM users WHERE role = 'member'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

// ==========================================
// CLASS & TRAINER METRICS
// ==========================================

function getTotalClassesCount($conn) {
    $query = "SELECT COUNT(*) as count FROM classes";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

function getUpcomingClasses($conn, $limit = 10) {
    $query = "
        SELECT 
            c.id,
            c.title,
            c.class_date,
            c.start_time,
            c.end_time,
            c.capacity,
            t.user_id as trainer_id,
            u.name as trainer_name,
            (SELECT COUNT(*) FROM class_bookings WHERE class_id = c.id AND status = 'booked') as booked_count
        FROM classes c
        JOIN trainers t ON c.trainer_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE c.class_date >= CURDATE()
        ORDER BY c.class_date ASC, c.start_time ASC
        LIMIT " . intval($limit);
    
    $result = mysqli_query($conn, $query);
    $classes = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $classes[] = $row;
    }
    return $classes;
}

function getTrainerStats($conn) {
    $query = "
        SELECT 
            u.id,
            u.name,
            t.specialty,
            COUNT(c.id) as total_classes,
            (SELECT COUNT(*) FROM class_bookings cb JOIN classes c2 ON cb.class_id = c2.id WHERE c2.trainer_id = t.id AND cb.status = 'booked') as total_bookings
        FROM users u
        JOIN trainers t ON u.id = t.user_id
        LEFT JOIN classes c ON t.id = c.trainer_id
        GROUP BY u.id, u.name, t.specialty
        ORDER BY total_classes DESC
    ";
    
    $result = mysqli_query($conn, $query);
    $trainers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $trainers[] = $row;
    }
    return $trainers;
}

// ==========================================
// ATTENDANCE METRICS
// ==========================================

function getTotalAttendanceThisMonth($conn) {
    $query = "
        SELECT COUNT(*) as count 
        FROM attendance 
        WHERE YEAR(checked_in) = YEAR(CURDATE()) 
        AND MONTH(checked_in) = MONTH(CURDATE())
    ";
    
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] ?? 0;
}

function getAttendanceByDay($conn, $days = 30) {
    $query = "
        SELECT 
            DATE(checked_in) as date,
            COUNT(*) as count
        FROM attendance
        WHERE checked_in >= DATE_SUB(CURDATE(), INTERVAL " . intval($days) . " DAY)
        GROUP BY DATE(checked_in)
        ORDER BY date DESC
    ";
    
    $result = mysqli_query($conn, $query);
    $attendance = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $attendance[] = $row;
    }
    return $attendance;
}

function getPeakAttendanceHours($conn) {
    $query = "
        SELECT 
            HOUR(checked_in) as hour,
            COUNT(*) as count
        FROM attendance
        WHERE YEAR(checked_in) = YEAR(CURDATE()) 
        AND MONTH(checked_in) = MONTH(CURDATE())
        GROUP BY HOUR(checked_in)
        ORDER BY count DESC
        LIMIT 5
    ";
    
    $result = mysqli_query($conn, $query);
    $hours = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $hours[] = $row;
    }
    return $hours;
}

function getMemberAttendanceFrequency($conn, $limit = 10) {
    $query = "
        SELECT 
            u.id,
            u.name,
            u.email,
            COUNT(a.id) as visits,
            MAX(a.checked_in) as last_visit,
            ROUND(COUNT(a.id) / DATEDIFF(CURDATE(), u.created_at), 2) as avg_visits_per_day
        FROM users u
        LEFT JOIN attendance a ON u.id = a.user_id
        WHERE u.role = 'member'
        GROUP BY u.id, u.name, u.email, u.created_at
        ORDER BY visits DESC
        LIMIT " . intval($limit);
    
    $result = mysqli_query($conn, $query);
    $frequency = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $frequency[] = $row;
    }
    return $frequency;
}

// ==========================================
// TRANSACTION METRICS
// ==========================================

function getRecentPayments($conn, $limit = 20) {
    $query = "
        SELECT 
            p.id,
            p.amount,
            p.method,
            p.status,
            p.paid_at,
            u.name,
            u.email,
            pl.name as plan_name
        FROM payments p
        JOIN users u ON p.user_id = u.id
        JOIN member_plans mp ON p.member_plan_id = mp.id
        JOIN membership_plans pl ON mp.plan_id = pl.id
        ORDER BY p.paid_at DESC
        LIMIT " . intval($limit);
    
    $result = mysqli_query($conn, $query);
    $payments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $payments[] = $row;
    }
    return $payments;
}

function getPaymentStats($conn) {
    $query = "
        SELECT 
            status,
            COUNT(*) as count,
            SUM(amount) as total
        FROM payments
        WHERE YEAR(paid_at) = YEAR(CURDATE()) 
        AND MONTH(paid_at) = MONTH(CURDATE())
        GROUP BY status
    ";
    
    $result = mysqli_query($conn, $query);
    $stats = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $stats[] = $row;
    }
    return $stats;
}

// ==========================================
// UTILITY FUNCTIONS
// ==========================================

function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

function formatDate($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('M d, Y H:i', strtotime($datetime));
}

function getPercentageChange($current, $previous) {
    if ($previous == 0) return 0;
    return round((($current - $previous) / $previous) * 100, 2);
}

function formatPercentage($value) {
    return number_format($value, 2) . '%';
}

function getOccupancyPercentage($booked, $capacity) {
    if ($capacity == 0) return 0;
    return round(($booked / $capacity) * 100);
}

?>
