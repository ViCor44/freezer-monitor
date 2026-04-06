<?php

class DashboardController {
    private $db;
    private $deviceModel;
    private $readingModel;
    private $alertModel;
    private $noteModel;

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
        $this->noteModel = new Note($db);
    }

    public function index() {
        Auth::requireApproved();

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

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

        $device = $this->deviceModel->findByIdWithTelemetry($deviceId);
        if (!$device) {
            header('Location: ' . BASE_URL . '/dashboard');
            exit;
        }

        $deviceNotes = $this->noteModel->getByDeviceAndRange(
            $deviceId,
            '1970-01-01 00:00:00',
            '2099-12-31 23:59:59'
        );

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
                'temp_max' => isset($device['temp_max']) ? (float) $device['temp_max'] : TEMP_MAX,
                'temp_min' => isset($device['temp_min']) ? (float) $device['temp_min'] : TEMP_MIN,
            ]);
            return;
        }

        switch ($period) {
            case '7d':
                $rows = $this->readingModel->getLast7Days($deviceId);
                $labels = array_map(static fn($row) => $row['recorded_at'], $rows);
                break;
            case '30d':
                $rows = $this->readingModel->getLast30Days($deviceId);
                $labels = array_map(static fn($row) => $row['recorded_at'], $rows);
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
            'temp_max' => isset($device['temp_max']) ? (float) $device['temp_max'] : TEMP_MAX,
            'temp_min' => isset($device['temp_min']) ? (float) $device['temp_min'] : TEMP_MIN,
        ]);
    }

    public function saveNote(): void {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $deviceId = (int) ($_POST['device_id'] ?? 0);
        $notedAt = $_POST['noted_at'] ?? '';
        $noteText = $_POST['note_text'] ?? '';

        if ($deviceId <= 0 || empty($notedAt) || empty($noteText)) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados invalidos']);
            exit;
        }

        // Validate device access
        $device = $this->deviceModel->findById($deviceId);
        if (!$device) {
            http_response_code(404);
            echo json_encode(['error' => 'Dispositivo nao encontrado']);
            exit;
        }

        try {
            $noteId = $this->noteModel->create(
                $deviceId,
                $notedAt,
                $noteText,
                (int) $_SESSION['user_id']
            );

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'note_id' => $noteId,
                'message' => 'Nota guardada com sucesso'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao guardar nota']);
        }
    }

    public function getNotes(): void {
        header('Content-Type: application/json');
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $deviceId = (int) ($_GET['device_id'] ?? 0);
        $from = $_GET['from'] ?? '';
        $to = $_GET['to'] ?? '';

        if ($deviceId <= 0 || empty($from) || empty($to)) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados invalidos']);
            exit;
        }

        // Validate device access
        $device = $this->deviceModel->findById($deviceId);
        if (!$device) {
            http_response_code(404);
            echo json_encode(['error' => 'Dispositivo nao encontrado']);
            exit;
        }

        try {
            $notes = $this->noteModel->getByDeviceAndRange($deviceId, $from, $to);
            echo json_encode([
                'success' => true,
                'notes' => $notes
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao recuperar notas']);
        }
    }

    public function devicesLiveData(): void {
        header('Content-Type: application/json');
        Auth::requireApproved();

        $devices = $this->deviceModel->getAll();
        $payload = array_map(fn($device) => $this->formatDeviceCardData($device), $devices);

        echo json_encode([
            'updated_at' => date('c'),
            'devices' => $payload,
        ]);
    }

    private function formatDeviceCardData(array $device): array {
        $lastSeen = $device['last_seen_at'] ?? $device['last_reading'] ?? null;
        $secondsSinceSeen = isset($device['seconds_since_seen']) ? (int) $device['seconds_since_seen'] : null;
        $secondsSinceReading = isset($device['seconds_since_reading']) ? (int) $device['seconds_since_reading'] : null;

        $isRecentlySeen = $secondsSinceSeen !== null
            && $secondsSinceSeen >= 0
            && $secondsSinceSeen <= (DEVICE_ONLINE_WINDOW_MINUTES * 60);

        $isOnline = !empty($device['active']) && $isRecentlySeen;

        $hasRecentTemperature = $device['last_temp'] !== null
            && $secondsSinceReading !== null
            && $secondsSinceReading >= 0
            && $secondsSinceReading <= (DEVICE_ONLINE_WINDOW_MINUTES * 60);

        $lastTemp = $device['last_temp'] !== null ? (float) $device['last_temp'] : null;
        $isTempAlert = $hasRecentTemperature
            && ($lastTemp > (float) $device['temp_max'] || $lastTemp < (float) $device['temp_min']);

        return [
            'id' => (int) $device['id'],
            'is_online' => $isOnline,
            'temperature' => $hasRecentTemperature ? $lastTemp : null,
            'temperature_text' => $hasRecentTemperature ? number_format($lastTemp, 1) . '°C' : '--',
            'range_badge_class' => !$hasRecentTemperature ? 'secondary' : ($isTempAlert ? 'danger' : 'success'),
            'range_badge_text' => !$hasRecentTemperature ? 'Sem dados recentes' : ($isTempAlert ? 'Fora do intervalo' : 'Dentro do intervalo'),
            'last_seen' => $lastSeen,
            'last_seen_text' => $lastSeen ? date('Y-m-d H:i', strtotime($lastSeen)) : 'N/A',
        ];
    }
}
?>