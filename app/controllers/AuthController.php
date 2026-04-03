<?php

class AuthController {
    private $db;
    private $userModel;

    public function __construct($db = null) {  // ← Adicione $db = null
        // Se não receber $db do router, tente obter da classe Database
        if ($db === null) {
            $database = new Database();
            $db = $database->connect();
        }
        $this->db = $db;
        $this->userModel = new User($db);  // ← Passe $db ao modelo
    }

    public function loginPage() {
        require ROOT . '/app/views/auth/login.php';
    }

    public function registerPage() {
        require ROOT . '/app/views/auth/register.php';
    }

    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->loginPage();
            return;
        }

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $user = $this->userModel->login($email, $password);

        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_approved'] = (int) ($user['approved'] ?? 0);

            if (empty($user['approved'])) {
                header('Location: ' . BASE_URL . '/pending');
            } else {
                header('Location: ' . BASE_URL . '/dashboard');
            }
            exit;
        } else {
            $error = 'Email ou palavra-passe invalido.';
            require ROOT . '/app/views/auth/login.php';
        }
    }

    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->registerPage();
            return;
        }

        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($password !== $confirm_password) {
            $error = 'As palavras-passe nao coincidem.';
            require ROOT . '/app/views/auth/register.php';
            return;
        }

        if ($this->userModel->register($name, $email, $password)) {
            $success = 'Registo efetuado com sucesso! Aguarda aprovacao de administrador.';
            require ROOT . '/app/views/auth/register.php';
        } else {
            $error = 'Falha no registo. O email pode ja existir.';
            require ROOT . '/app/views/auth/register.php';
        }
    }

    public function pending() {
        require ROOT . '/app/views/auth/pending.php';
    }

    public function logout() {
        session_destroy();
        header('Location: ' . BASE_URL . '/login');
        exit;
    }
}
?>