<?php

/**
 * General Utility Helper Functions
 */

function getCurrentPage() {
    return isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
}

function getPaginationOffset($page, $limit) {
    return ($page - 1) * $limit;
}

function getTotalPages($totalItems, $limit) {
    return ceil($totalItems / $limit);
}

function isAdmin($userRole) {
    return $userRole === 'admin';
}

function isTrainer($userRole) {
    return $userRole === 'trainer';
}

function isMember($userRole) {
    return $userRole === 'member';
}

function getStatusBadgeClass($status) {
    $statusMap = [
        'active' => 'badge-success',
        'inactive' => 'badge-secondary',
        'suspended' => 'badge-danger',
        'expired' => 'badge-warning',
        'cancelled' => 'badge-danger',
        'paid' => 'badge-success',
        'pending' => 'badge-warning',
        'failed' => 'badge-danger',
        'booked' => 'badge-info',
    ];
    
    return $statusMap[$status] ?? 'badge-secondary';
}

function getStatusLabel($status) {
    $labels = [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'suspended' => 'Suspended',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
        'paid' => 'Paid',
        'pending' => 'Pending',
        'failed' => 'Failed',
        'booked' => 'Booked',
    ];
    
    return $labels[$status] ?? ucfirst($status);
}

function truncateText($text, $length = 50) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

function getInitials($name) {
    $parts = explode(' ', $name);
    $initials = '';
    foreach ($parts as $part) {
        $initials .= strtoupper($part[0]);
    }
    return substr($initials, 0, 2);
}

function getMonthName($month) {
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    
    return $months[$month] ?? '';
}

function buildQueryString($params) {
    $query = [];
    foreach ($params as $key => $value) {
        if ($value !== null && $value !== '') {
            $query[] = urlencode($key) . '=' . urlencode($value);
        }
    }
    return implode('&', $query);
}

function getSortArrow($field, $current, $direction) {
    if ($field === $current) {
        return $direction === 'asc' ? ' ▲' : ' ▼';
    }
    return '';
}

?>
