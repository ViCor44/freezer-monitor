<?php

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/middleware/Auth.php';

class AuthController {
    private User $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function loginPage(): void {
        if (Auth::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }
        $csrf = Auth::csrfToken();
        $error = $_SESSION['flash_error'] ?? '';
        unset($_SESSION['flash_error']);
        require __DIR__ . '/../views/auth/login.php';
    }

    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!Auth::verifyCsrf($token)) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = $this->userModel->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $_SESSION['flash_error'] = 'Invalid email or password.';
            header('Location: ' . BASE_URL . '/login');
            exit;
        }

        session_regenerate_id(true);
        $_SESSION[SESSION_USER_ID]       = $user['id'];
        $_SESSION[SESSION_USER_ROLE]     = $user['role'];
        $_SESSION[SESSION_USER_NAME]     = $user['name'];
        $_SESSION[SESSION_USER_APPROVED] = (bool) $user['approved'];

        header('Location: ' . BASE_URL . '/dashboard');
        exit;
    }

    public function registerPage(): void {
        if (Auth::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }
        $csrf  = Auth::csrfToken();
        $error = $_SESSION['flash_error'] ?? '';
        unset($_SESSION['flash_error']);
        require __DIR__ . '/../views/auth/register.php';
    }

    public function register(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        $token = $_POST['csrf_token'] ?? '';
        if (!Auth::verifyCsrf($token)) {
            $_SESSION['flash_error'] = 'Invalid CSRF token.';
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';

        if (!$name || !$email || !$password) {
            $_SESSION['flash_error'] = 'All fields are required.';
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Invalid email address.';
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        if (strlen($password) < 8) {
            $_SESSION['flash_error'] = 'Password must be at least 8 characters.';
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        if ($password !== $confirm) {
            $_SESSION['flash_error'] = 'Passwords do not match.';
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        if ($this->userModel->findByEmail($email)) {
            $_SESSION['flash_error'] = 'Email already registered.';
            header('Location: ' . BASE_URL . '/register');
            exit;
        }

        $this->userModel->create($name, $email, $password);
        $_SESSION['flash_success'] = 'Registration successful. Please wait for admin approval.';
        header('Location: ' . BASE_URL . '/login');
        exit;
    }

    public function logout(): void {
        Auth::requireLogin();
        session_destroy();
        header('Location: ' . BASE_URL . '/login');
        exit;
    }

    public function pending(): void {
        Auth::requireLogin();
        require __DIR__ . '/../views/auth/pending.php';
    }
}
