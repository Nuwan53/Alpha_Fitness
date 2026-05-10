<?php

session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function login($user_id) {
    $_SESSION['user_id'] = $user_id;
}

function logout() {
    session_unset();
    session_destroy();
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireRole($requiredRole) {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
        header('Location: dashboard.php');
        exit();
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

?>
