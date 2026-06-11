<?php

// Start session
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// Auth guard - For protected pages that require login
function requireAuth(): void {
    startSecureSession();

    $maxIdleSeconds = 30 * 60;

    if (
        empty($_SESSION['logged_in']) ||
        empty($_SESSION['admin_id']) ||
        (time() - ($_SESSION['login_activity'] ?? 0)) > $maxIdleSeconds
    ) {
        session_unset();
        session_destroy();
        header('Location: /admin-login/login.php?reason=timeout');
        exit();
    }

    // Refresh activity timestamp on every request
    $_SESSION['login_activity'] = time();
}