<?php
// ========================================
// AUTHENTICATION SYSTEM
// ========================================

/**
 * Initialize or resume session
 */
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    initSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user data
 */
function getCurrentUser(): ?array {
    initSession();
    if (!isLoggedIn()) return null;
    
    $users = loadData('users');
    return $users[$_SESSION['user_id']] ?? null;
}

/**
 * Login user
 */
function login(string $email, string $password): bool {
    initSession();
    
    $users = loadData('users');
    
    foreach ($users as $id => $user) {
        if ($user['email'] === $email && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $id;
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            return true;
        }
    }
    
    return false;
}

/**
 * Logout user
 */
function logout(): void {
    initSession();
    session_destroy();
    header('Location: login.php');
    exit;
}

/**
 * Require login - redirect to login page if not authenticated
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit;
    }
}

/**
 * Create a new user (for setup or registration)
 */
function createUser(string $email, string $password, string $name, string $role = 'user'): array {
    $users = loadData('users');
    
    // Check if user already exists
    foreach ($users as $user) {
        if ($user['email'] === $email) {
            return ['success' => false, 'error' => 'User already exists'];
        }
    }
    
    // Generate unique ID
    $id = 'user_' . uniqid();
    
    $users[$id] = [
        'id' => $id,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'name' => $name,
        'role' => $role,
        'created_at' => date('Y-m-d H:i:s'),
        'last_login' => null
    ];
    
    if (saveData('users', $users)) {
        return ['success' => true, 'user_id' => $id];
    }
    
    return ['success' => false, 'error' => 'Failed to create user'];
}

/**
 * Update last login
 */
function updateLastLogin(): void {
    if (!isLoggedIn()) return;
    
    $users = loadData('users');
    if (isset($users[$_SESSION['user_id']])) {
        $users[$_SESSION['user_id']]['last_login'] = date('Y-m-d H:i:s');
        saveData('users', $users);
    }
}

/**
 * Check if user has permission
 */
function hasRole(string $role): bool {
    $user = getCurrentUser();
    return $user && ($user['role'] === $role || $user['role'] === 'admin');
}
