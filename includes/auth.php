<?php
/**
 * Authentication & Session Management
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Get current user ID
function getCurrentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

// Get current user role
function getCurrentUserRole(): ?string {
    return $_SESSION['user_role'] ?? null;
}

// Get current user name
function getCurrentUserName(): ?string {
    return $_SESSION['user_name'] ?? null;
}

// Require login - redirect to login page if not authenticated
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /prakchek_/login.php');
        exit;
    }
}

// Require specific role
function requireRole(string $role): void {
    requireLogin();
    if (getCurrentUserRole() !== $role) {
        header('Location: /prakchek_/dashboard.php');
        exit;
    }
}

// Login user
function loginUser(int $userId, string $userName, string $userRole): void {
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $userName;
    $_SESSION['user_role'] = $userRole;

    // Regenerate session ID for security
    session_regenerate_id(true);
}

// Logout user
function logoutUser(): void {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

// Generate CSRF token
function generateCsrfToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Get CSRF token input field
function csrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}
