<?php
// API controller para polling de última leitura de um dispositivo
require_once __DIR__ . '/../../models/Device.php';
require_once __DIR__ . '/../../models/TemperatureReading.php';
require_once __DIR__ . '/../../middleware/Auth.php';

class DeviceApiController {
    public function lastReading() {
        header('Content-Type: application/json');
        Auth::requireApproved();
        $deviceId = isset($_GET['device_id']) ? (int)$_GET['device_id'] : 0;
        if ($deviceId <= 0) {
            echo json_encode(['error' => 'ID inválido']);
            return;
        }
        $db = (new Database())->connect();
        $readingModel = new TemperatureReading($db);
        $reading = $readingModel->getLastByDevice($deviceId);
        if (!$reading) {
            echo json_encode(['error' => 'Sem leitura']);
            return;
        }
        echo json_encode([
            'timestamp' => $reading['created_at'],
            'id' => $reading['id'],
        ]);
    }
}
