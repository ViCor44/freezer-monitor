<?php

class DashboardController {
    private $db;
    private $deviceModel;
    private $readingModel;
    private $alertModel;

    public function __construct($db = null) {
        if ($db === null) {
            $database = new Database();
            $db = $database->connect();
        }
        
        $this->db = $db;
        
        // ✅ PASSAR $db AO MODELO
        $this->deviceModel = new Device($db);
        $this->readingModel = new TemperatureReading($db);
        $this->alertModel = new Alert($db);
    }

    public function index() {
        Auth::requireApproved();

        $devices = $this->deviceModel->getAll();
        $deviceCount = $this->deviceModel->count();
        $todayCount = $this->readingModel->countToday();
        $openAlerts = $this->alertModel->countOpen();

        require ROOT . '/app/views/dashboard/index.php';
    }

    public function deviceDetails() {
        Auth::requireApproved();

        $deviceId = (int) ($_GET['id'] ?? 0);
        if ($deviceId <= 0) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $device = $this->deviceModel->findById($deviceId);
        if (!$device) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        require ROOT . '/app/views/dashboard/device_details.php';
    }

    public function chartData() {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $deviceId = (int) ($_GET['device_id'] ?? 0);
        $period = $_GET['period'] ?? '24h';

        if ($deviceId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Dispositivo invalido']);
            exit;
        }

        $device = $this->deviceModel->findById($deviceId);
        if (!$device) {
            http_response_code(404);
            echo json_encode(['error' => 'Dispositivo nao encontrado']);
            exit;
        }

        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';

        if ($from !== '' && $to !== '') {
            $fromTs = strtotime($from);
            $toTs = strtotime($to);

            if ($fromTs === false || $toTs === false || $fromTs > $toTs) {
                http_response_code(400);
                echo json_encode(['error' => 'Intervalo de datas invalido']);
                exit;
            }

            $rows = $this->readingModel->getByRange(
                $deviceId,
                date('Y-m-d H:i:s', $fromTs),
                date('Y-m-d H:i:s', $toTs)
            );
            $labels = array_map(static fn($row) => $row['recorded_at'], $rows);

            echo json_encode([
                'labels' => $labels,
                'temperature' => array_map(static fn($row) => $row['temperature'] !== null ? (float) $row['temperature'] : null, $rows),
                'humidity' => array_map(static fn($row) => $row['humidity'] !== null ? (float) $row['humidity'] : null, $rows),
                'temp_max' => isset($device['temp_max']) ? (float) $device['temp_max'] : TEMP_MAX,
                'temp_min' => isset($device['temp_min']) ? (float) $device['temp_min'] : TEMP_MIN,
            ]);
            return;
        }

        switch ($period) {
            case '7d':
                $rows = $this->readingModel->getLast7Days($deviceId);
                $labels = array_map(static fn($row) => $row['recorded_date'], $rows);
                break;
            case '30d':
                $rows = $this->readingModel->getLast30Days($deviceId);
                $labels = array_map(static fn($row) => $row['recorded_date'], $rows);
                break;
            case '24h':
            default:
                $rows = $this->readingModel->getLast24Hours($deviceId);
                $labels = array_map(static fn($row) => $row['recorded_at'], $rows);
                break;
        }

        echo json_encode([
            'labels' => $labels,
            'temperature' => array_map(static fn($row) => $row['temperature'] !== null ? (float) $row['temperature'] : null, $rows),
            'humidity' => array_map(static fn($row) => $row['humidity'] !== null ? (float) $row['humidity'] : null, $rows),
            'temp_max' => isset($device['temp_max']) ? (float) $device['temp_max'] : TEMP_MAX,
            'temp_min' => isset($device['temp_min']) ? (float) $device['temp_min'] : TEMP_MIN,
        ]);
    }
}
?>