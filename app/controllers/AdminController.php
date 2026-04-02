<?php

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../app/models/User.php';
require_once __DIR__ . '/../../app/models/Device.php';
require_once __DIR__ . '/../../app/models/Alert.php';
require_once __DIR__ . '/../../app/middleware/Auth.php';

class AdminController {
    private User $userModel;
    private Device $deviceModel;
    private Alert $alertModel;

    public function __construct() {
        $this->userModel   = new User();
        $this->deviceModel = new Device();
        $this->alertModel  = new Alert();
    }

    // ── Users ──────────────────────────────────────────────────────────────

    public function users(): void {
        Auth::requireAdmin();
        $users = $this->userModel->getAll();
        require __DIR__ . '/../views/admin/users.php';
    }

    public function approveUser(): void {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $this->userModel->approve($id);
        }
        header('Location: ' . BASE_URL . '/admin/users');
        exit;
    }

    public function revokeUser(): void {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id && $id !== Auth::userId()) {
            $this->userModel->revoke($id);
        }
        header('Location: ' . BASE_URL . '/admin/users');
        exit;
    }

    public function deleteUser(): void {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id && $id !== Auth::userId()) {
            $this->userModel->delete($id);
        }
        header('Location: ' . BASE_URL . '/admin/users');
        exit;
    }

    // ── Devices ────────────────────────────────────────────────────────────

    public function devices(): void {
        Auth::requireAdmin();
        $devices = $this->deviceModel->getAll();
        require __DIR__ . '/../views/admin/devices.php';
    }

    public function createDevice(): void {
        Auth::requireAdmin();
        $this->verifyCsrf();

        $name     = trim($_POST['name'] ?? '');
        $devEui   = trim($_POST['dev_eui'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $tempMax  = (float) ($_POST['temp_max'] ?? TEMP_MAX);
        $tempMin  = (float) ($_POST['temp_min'] ?? TEMP_MIN);

        if ($name && $devEui) {
            $this->deviceModel->create($name, $devEui, $location, $tempMax, $tempMin);
        }

        header('Location: ' . BASE_URL . '/admin/devices');
        exit;
    }

    public function updateDevice(): void {
        Auth::requireAdmin();
        $this->verifyCsrf();

        $id       = (int) ($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $tempMax  = (float) ($_POST['temp_max'] ?? TEMP_MAX);
        $tempMin  = (float) ($_POST['temp_min'] ?? TEMP_MIN);
        $active   = (int) ($_POST['active'] ?? 0);

        if ($id && $name) {
            $this->deviceModel->update($id, $name, $location, $tempMax, $tempMin, $active);
        }

        header('Location: ' . BASE_URL . '/admin/devices');
        exit;
    }

    public function deleteDevice(): void {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $this->deviceModel->delete($id);
        }
        header('Location: ' . BASE_URL . '/admin/devices');
        exit;
    }

    // ── Alerts ─────────────────────────────────────────────────────────────

    public function alerts(): void {
        Auth::requireAdmin();
        $status = $_GET['status'] ?? '';
        $alerts = $this->alertModel->getAll($status);
        require __DIR__ . '/../views/admin/alerts.php';
    }

    public function acknowledgeAlert(): void {
        Auth::requireApproved();
        $this->verifyCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $this->alertModel->acknowledge($id, Auth::userId());
        }
        header('Location: ' . BASE_URL . '/admin/alerts');
        exit;
    }

    public function resolveAlert(): void {
        Auth::requireAdmin();
        $this->verifyCsrf();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id) {
            $this->alertModel->resolve($id);
        }
        header('Location: ' . BASE_URL . '/admin/alerts');
        exit;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function verifyCsrf(): void {
        $token = $_POST['csrf_token'] ?? '';
        if (!Auth::verifyCsrf($token)) {
            http_response_code(403);
            exit('Forbidden');
        }
    }
}
