<?php

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../app/models/Device.php';
require_once __DIR__ . '/../../app/models/TemperatureReading.php';
require_once __DIR__ . '/../../app/models/Alert.php';

class WebhookController {
    private $db;
    private Device $deviceModel;
    private TemperatureReading $readingModel;
    private Alert $alertModel;

    public function __construct($db = null) {
        if ($db === null) {
            $database = new Database();
            $db = $database->connect();
        }

        $this->db = $db;
        $this->deviceModel = new Device($db);
        $this->readingModel = new TemperatureReading($db);
        $this->alertModel = new Alert($db);
    }

    /**
     * POST /webhook/chirpstack
     * Receives uplink data from ChirpStack and stores temperature readings.
     */
    public function chirpstack(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        if (!$payload || !isset($payload['deviceInfo']['devEui'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Payload invalido']);
            exit;
        }

        $devEui = strtolower($payload['deviceInfo']['devEui']);
        $device = $this->deviceModel->findByDevEui($devEui);

        if (!$device || !$device['active']) {
            http_response_code(404);
            echo json_encode(['error' => 'Dispositivo nao encontrado ou inativo']);
            exit;
        }

        // Decode base64 object data
        $objectData = $payload['object'] ?? [];
        $rxInfo     = $payload['rxInfo'][0] ?? [];

        $temperature = isset($objectData['temperature']) ? (float) $objectData['temperature'] : null;
        $humidity    = isset($objectData['humidity'])    ? (float) $objectData['humidity']    : null;
        $battery     = isset($objectData['battery'])     ? (float) $objectData['battery']     : null;
        $rssi        = isset($rxInfo['rssi'])             ? (int)   $rxInfo['rssi']            : null;
        $snr         = isset($rxInfo['snr'])              ? (float) $rxInfo['snr']             : null;

        if ($temperature === null) {
            http_response_code(422);
            echo json_encode(['error' => 'Sem temperatura no payload']);
            exit;
        }

        $readingId = $this->readingModel->create(
            $device['id'], $temperature, $humidity, $battery, $rssi, $snr
        );
        $this->deviceModel->updateLastSeen($device['id']);

        // Auto-generate alerts
        $this->checkAlerts($device, $temperature);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'reading_id' => $readingId]);
        exit;
    }

    private function checkAlerts(array $device, float $temperature): void {
        if ($temperature > $device['temp_max']) {
            if (!$this->alertModel->hasOpenAlert($device['id'], ALERT_TEMP_HIGH)) {
                $this->alertModel->create(
                    $device['id'],
                    ALERT_TEMP_HIGH,
                    "Temperature {$temperature}°C exceeds maximum {$device['temp_max']}°C on {$device['name']}",
                    $temperature
                );
            }
        } elseif ($temperature < $device['temp_min']) {
            if (!$this->alertModel->hasOpenAlert($device['id'], ALERT_TEMP_LOW)) {
                $this->alertModel->create(
                    $device['id'],
                    ALERT_TEMP_LOW,
                    "Temperature {$temperature}°C below minimum {$device['temp_min']}°C on {$device['name']}",
                    $temperature
                );
            }
        }
    }
}
