<?php

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../app/models/Device.php';
require_once __DIR__ . '/../../app/models/TemperatureReading.php';
require_once __DIR__ . '/../../app/models/Alert.php';
require_once __DIR__ . '/../../app/models/DoorOpening.php';

class WebhookController {
    private $db;
    private Device $deviceModel;
    private TemperatureReading $readingModel;
    private Alert $alertModel;
    private DoorOpening $doorOpeningModel;

    public function __construct($db = null) {
        if ($db === null) {
            $database = new Database();
            $db = $database->connect();
        }

        $this->db = $db;
        $this->deviceModel = new Device($db);
        $this->readingModel = new TemperatureReading($db);
        $this->alertModel = new Alert($db);
        $this->doorOpeningModel = new DoorOpening($db);
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
        $this->logIncomingPayload($raw);
        $payload = json_decode($raw, true);

        if (!$payload) {
            http_response_code(400);
            echo json_encode(['error' => 'Payload invalido']);
            exit;
        }

        $uplink = [];
        if (isset($payload['uplink_message']) && is_array($payload['uplink_message'])) {
            $uplink = $payload['uplink_message'];
        } elseif (isset($payload['uplinkMessage']) && is_array($payload['uplinkMessage'])) {
            $uplink = $payload['uplinkMessage'];
        }

        $devEui = $payload['deviceInfo']['devEui']
            ?? $payload['device_info']['dev_eui']
            ?? $uplink['deviceInfo']['devEui']
            ?? $uplink['device_info']['dev_eui']
            ?? null;

        if (!is_string($devEui) || trim($devEui) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Device EUI ausente no payload']);
            exit;
        }

        $devEui = strtolower($devEui);
        $device = $this->deviceModel->findByDevEui($devEui);

        if (!$device || !$device['active']) {
            http_response_code(404);
            echo json_encode(['error' => 'Dispositivo nao encontrado ou inativo']);
            exit;
        }

        // Registos pausados: atualiza last_seen mas nao guarda temperaturas.
        if (!empty($device['recordings_paused'])) {
            $this->deviceModel->updateLastSeen((int) $device['id']);
            http_response_code(202);
            echo json_encode(['status' => 'recordings_paused']);
            exit;
        }

        // Mark the device as online as soon as any uplink is received.
        $this->deviceModel->updateLastSeen((int) $device['id']);

        // Accept common decoded payload keys produced by ChirpStack codecs.
        $objectData = $payload['object']
            ?? $payload['decodedObject']
            ?? $payload['decoded_object']
            ?? $uplink['object']
            ?? $uplink['decodedObject']
            ?? $uplink['decoded_object']
            ?? [];
        $rxInfo = $payload['rxInfo'][0]
            ?? $payload['rx_info'][0]
            ?? $uplink['rxInfo'][0]
            ?? $uplink['rx_info'][0]
            ?? [];

        $temperature = $this->extractTemperature($payload, $objectData);
        $doorOpen    = $this->extractDoorOpen($payload, $objectData);
        $humidity    = isset($objectData['humidity'])    ? (float) $objectData['humidity']    : null;
        $battery     = isset($objectData['battery'])     ? (float) $objectData['battery']     : null;
        $rssi        = isset($rxInfo['rssi'])             ? (int)   $rxInfo['rssi']            : null;
        $snr         = isset($rxInfo['snr'])              ? (float) $rxInfo['snr']             : null;

        $shouldCreateDoorOpening = false;
        if ($doorOpen !== null) {
            $doorOpenInt = $doorOpen ? 1 : 0;
            $previousDoorOpen = isset($device['door_open']) ? (int) $device['door_open'] : 0;
            $shouldCreateDoorOpening = $doorOpenInt === 1 && $previousDoorOpen !== 1;
            $this->deviceModel->updateDoorStatus((int) $device['id'], $doorOpenInt);
        }

        if ($temperature === null) {
            if ($shouldCreateDoorOpening) {
                $this->doorOpeningModel->create((int) $device['id'], null);
            }

            // Keep device online even when payload decoder does not expose temperature.
            http_response_code(202);
            echo json_encode(['status' => 'seen_no_temperature']);
            exit;
        }

        $readingId = $this->readingModel->create(
            $device['id'], $temperature, $humidity, $battery, $rssi, $snr
        );

        if ($shouldCreateDoorOpening) {
            $this->doorOpeningModel->create((int) $device['id'], $readingId);
        }

        // Auto-generate alerts
        $this->checkAlerts($device, $temperature);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok', 'reading_id' => $readingId]);
        exit;
    }

    private function logIncomingPayload(string $raw): void {
        $debugEnabled = getenv('WEBHOOK_DEBUG') ?: '';
        $debugEnabled = trim((string) $debugEnabled, " \t\n\r\0\x0B\"'");

        $isDebugEnabled = in_array(strtolower($debugEnabled), ['1', 'true', 'on', 'yes'], true);
        if (!$isDebugEnabled) {
            return;
        }

        $logDir = ROOT . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $logFile = $logDir . '/chirpstack-webhook.log';
        if (!file_exists($logFile)) {
            @touch($logFile);
        }

        $line = sprintf(
            "[%s] %s%s",
            date('Y-m-d H:i:s'),
            trim($raw),
            PHP_EOL
        );

        @file_put_contents($logFile, $line, FILE_APPEND);
    }

    private function extractTemperature(array $payload, array $objectData): ?float {
        $rawFieldDetected = false;
        $fromRawPayload = $this->extractTemperatureFromRawPayload($payload, $rawFieldDetected);
        if ($rawFieldDetected) {
            // If raw payload is present, trust only the sketch format parsing.
            // This prevents stale/incorrect codec-decoded values from overriding binary payload.
            return $fromRawPayload;
        }

        return $this->extractTemperatureFromObject($objectData);
    }

    private function extractTemperatureFromObject(array $objectData): ?float {
        $candidateKeys = ['temperature', 'temp', 'temperatura', 'Temperature'];

        foreach ($candidateKeys as $key) {
            if (array_key_exists($key, $objectData) && is_numeric($objectData[$key])) {
                return (float) $objectData[$key];
            }
        }

        if (isset($objectData['sensor']) && is_array($objectData['sensor'])) {
            foreach ($candidateKeys as $key) {
                if (array_key_exists($key, $objectData['sensor']) && is_numeric($objectData['sensor'][$key])) {
                    return (float) $objectData['sensor'][$key];
                }
            }
        }

        return null;
    }

    private function extractTemperatureFromRawPayload(array $payload, bool &$rawFieldDetected = false): ?float {
        $binary = $this->extractSketchBinaryPayload($payload, $rawFieldDetected);
        if ($binary === null) {
            return null;
        }

        return $this->parseTemperatureFromSketchBinary($binary);
    }

    private function extractSketchBinaryPayload(array $payload, bool &$rawFieldDetected = false): ?string {
        $uplink = [];
        if (isset($payload['uplink_message']) && is_array($payload['uplink_message'])) {
            $uplink = $payload['uplink_message'];
        } elseif (isset($payload['uplinkMessage']) && is_array($payload['uplinkMessage'])) {
            $uplink = $payload['uplinkMessage'];
        }

        $candidateBase64Values = [
            $payload['data'] ?? null,
            $payload['frmPayload'] ?? null,
            $payload['frm_payload'] ?? null,
            $payload['payload'] ?? null,
            $uplink['data'] ?? null,
            $uplink['frmPayload'] ?? null,
            $uplink['frm_payload'] ?? null,
            $uplink['payload'] ?? null,
        ];

        foreach ($candidateBase64Values as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $rawFieldDetected = true;

            $binary = $this->decodeBase64Payload($candidate);
            if ($binary === false) {
                continue;
            }

            return $binary;
        }

        $candidateBytesArrays = [
            $payload['bytes'] ?? null,
            $payload['dataBytes'] ?? null,
            $payload['data_bytes'] ?? null,
            $uplink['bytes'] ?? null,
            $uplink['dataBytes'] ?? null,
            $uplink['data_bytes'] ?? null,
        ];

        foreach ($candidateBytesArrays as $bytes) {
            if (!is_array($bytes)) {
                continue;
            }

            $rawFieldDetected = true;

            $binary = '';
            foreach ($bytes as $byte) {
                if (!is_numeric($byte)) {
                    $binary = '';
                    break;
                }
                $binary .= chr(((int) $byte) & 0xFF);
            }

            if ($binary === '') {
                continue;
            }

            return $binary;
        }

        return null;
    }

    private function parseTemperatureFromSketchBinary(string $binary): ?float {
        $size = strlen($binary);

        // Current device sketch sends 2 bytes temperature, optionally 1 extra byte in future.
        if ($size !== 2 && $size !== 3) {
            return null;
        }

        $value = unpack('nvalue', substr($binary, 0, 2));
        if (!is_array($value) || !isset($value['value'])) {
            return null;
        }

        $raw = (int) $value['value'];
        if ($raw >= 0x8000) {
            $raw -= 0x10000;
        }

        // 0x8000 indicates an invalid sensor read in the device sketch.
        if ($raw === -32768) {
            return null;
        }

        return $raw / 100.0;
    }

    private function decodeBase64Payload(string $encodedPayload): string|false {
        $encoded = trim($encodedPayload);
        if ($encoded === '') {
            return false;
        }

        // Accept URL-safe base64 and missing padding commonly seen in integrations.
        $encoded = strtr($encoded, '-_', '+/');
        $padding = strlen($encoded) % 4;
        if ($padding > 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded !== false) {
            return $decoded;
        }

        return base64_decode($encoded, false);
    }

    private function extractDoorOpen(array $payload, array $objectData): ?bool {
        $rawFieldDetected = false;
        $binary = $this->extractSketchBinaryPayload($payload, $rawFieldDetected);
        if ($rawFieldDetected) {
            return $this->parseDoorOpenFromSketchBinary($binary);
        }

        $candidateKeys = ['door_open', 'doorOpen', 'door', 'porta_aberta', 'contact', 'reed'];

        foreach ($candidateKeys as $key) {
            if (!array_key_exists($key, $objectData)) {
                continue;
            }

            $value = $objectData[$key];
            $parsed = $this->parseDoorValue($value);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        if (isset($objectData['sensor']) && is_array($objectData['sensor'])) {
            foreach ($candidateKeys as $key) {
                if (!array_key_exists($key, $objectData['sensor'])) {
                    continue;
                }

                $value = $objectData['sensor'][$key];
                $parsed = $this->parseDoorValue($value);
                if ($parsed !== null) {
                    return $parsed;
                }
            }
        }

        return null;
    }

    private function parseDoorOpenFromSketchBinary(?string $binary): ?bool {
        if ($binary === null) {
            return null;
        }

        if (strlen($binary) < 3) {
            return null;
        }

        return (ord($binary[2]) & 0x01) === 0x01;
    }

    private function parseDoorValue($value): ?bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((float) $value) > 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            $openValues = ['open', 'opened', 'aberta', 'aberto', 'true', '1', 'on'];
            $closedValues = ['closed', 'fechada', 'fechado', 'false', '0', 'off'];

            if (in_array($normalized, $openValues, true)) {
                return true;
            }

            if (in_array($normalized, $closedValues, true)) {
                return false;
            }
        }

        return null;
    }

    private function checkAlerts(array $device, float $temperature): void {
        if ($this->alertModel->hasAnyOpenAlert((int) $device['id'])) {
            return;
        }

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
