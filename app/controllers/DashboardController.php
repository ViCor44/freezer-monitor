<?php

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../app/models/Device.php';
require_once __DIR__ . '/../../app/models/TemperatureReading.php';
require_once __DIR__ . '/../../app/models/Alert.php';
require_once __DIR__ . '/../../app/middleware/Auth.php';

class DashboardController {
    private Device $deviceModel;
    private TemperatureReading $readingModel;
    private Alert $alertModel;

    public function __construct() {
        $this->deviceModel  = new Device();
        $this->readingModel = new TemperatureReading();
        $this->alertModel   = new Alert();
    }

    public function index(): void {
        Auth::requireApproved();

        $devices      = $this->deviceModel->getAll();
        $openAlerts   = $this->alertModel->countOpen();
        $todayCount   = $this->readingModel->countToday();
        $deviceCount  = $this->deviceModel->count();
        $latestPerDevice = $this->readingModel->getLatestPerDevice();

        require __DIR__ . '/../views/dashboard/index.php';
    }

    public function chartData(): void {
        Auth::requireApproved();

        $deviceId = (int) ($_GET['device_id'] ?? 0);
        $period   = $_GET['period'] ?? '24h';

        if (!$deviceId) {
            $this->jsonError('Invalid device');
        }

        $device = $this->deviceModel->findById($deviceId);
        if (!$device) {
            $this->jsonError('Device not found');
        }

        $readings = match($period) {
            '7d'  => $this->readingModel->getLast7Days($deviceId),
            '30d' => $this->readingModel->getLast30Days($deviceId),
            default => $this->readingModel->getLast24Hours($deviceId),
        };

        $labels = [];
        $temps  = [];
        $humids = [];

        foreach ($readings as $r) {
            $labels[] = $r['recorded_at'] ?? $r['recorded_date'];
            $temps[]  = (float) $r['temperature'];
            $humids[] = isset($r['humidity']) ? (float) $r['humidity'] : null;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'labels'      => $labels,
            'temperature' => $temps,
            'humidity'    => $humids,
            'temp_max'    => (float) $device['temp_max'],
            'temp_min'    => (float) $device['temp_min'],
        ]);
        exit;
    }

    private function jsonError(string $message): never {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => $message]);
        exit;
    }
}
