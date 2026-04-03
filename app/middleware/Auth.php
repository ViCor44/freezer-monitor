<?php

require_once __DIR__ . '/../../config/constants.php';

class Auth {
    public static function requireLogin(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
    }

    public static function requireApproved(): void {
        self::requireLogin();

        if (empty($_SESSION['user_approved'])) {
            header('Location: ' . BASE_URL . '/pending');
            exit;
        }
    }

    public static function requireAdmin(): void {
        self::requireApproved();

        if (($_SESSION['user_role'] ?? null) !== ROLE_ADMIN) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }
    }

    public static function isLoggedIn(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['user_id']);
    }

    public static function isAdmin(): bool {
        return self::isLoggedIn() && ($_SESSION['user_role'] ?? null) === ROLE_ADMIN;
    }

    public static function userId(): ?int {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function csrfToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
