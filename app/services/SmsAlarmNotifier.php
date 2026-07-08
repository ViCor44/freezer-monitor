<?php
/**
 * Notificador de SMS para dispositivos com temperatura continuamente fora do
 * intervalo definido (min/max).
 *
 * Fluxo (invocado a cada leitura recebida no webhook):
 *   1. Actualiza a tabela `device_temp_alarm_state` com o estado atual do
 *      dispositivo:
 *        - Se a temperatura esta fora do intervalo -> mantem/insere o estado
 *          com o mesmo tipo (temp_high/temp_low).
 *        - Se transita para outro tipo, reinicia first_active_at.
 *        - Se voltou a estar dentro do intervalo, limpa o estado (e envia SMS
 *          de recuperacao "[OK]" quando ja tinha sido enviado o [ALARME]).
 *   2. Se o estado ja existe ha >= SMS_ALARM_MIN_MINUTES minutos e ainda nao
 *      foi enviado o SMS, envia [ALARME] para todos os utilizadores que
 *      optaram por receber (users.receive_sms_alarms=1 e phone preenchido) e
 *      marca sms_sent_at.
 *   3. Todas as tentativas (sent/failed/skipped) sao registadas em sms_log.
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/TeltonikaSmsClient.php';

class SmsAlarmNotifier
{
    private PDO $db;
    private ?TeltonikaSmsClient $client = null;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Processa uma leitura recem-chegada de um dispositivo.
     * $device deve conter pelo menos: id, name, temp_min, temp_max.
     */
    public function processReading(array $device, float $temperature): void
    {
        if (!defined('SMS_ENABLED') || !SMS_ENABLED) {
            return;
        }
        if (!$this->tablesExist()) {
            return;
        }

        $deviceId = (int) $device['id'];
        $tempMax  = isset($device['temp_max']) ? (float) $device['temp_max'] : (float) TEMP_MAX;
        $tempMin  = isset($device['temp_min']) ? (float) $device['temp_min'] : (float) TEMP_MIN;

        $currentType = null;
        if ($temperature > $tempMax) {
            $currentType = 'temp_high';
        } elseif ($temperature < $tempMin) {
            $currentType = 'temp_low';
        }

        $prev = $this->getState($deviceId);

        // Caso 1: voltou ao normal.
        if ($currentType === null) {
            if ($prev !== null) {
                if ($prev['sms_sent_at'] !== null) {
                    $this->sendToRecipients(
                        $device,
                        (string) $prev['alarm_type'],
                        'OK',
                        $temperature,
                        (string) $prev['first_active_at']
                    );
                }
                $this->clearState($deviceId);
            }
            return;
        }

        // Caso 2: continua fora do intervalo (mesmo tipo).
        if ($prev !== null && $prev['alarm_type'] === $currentType) {
            $this->touchState($deviceId, $temperature);
        }
        // Caso 3: mudou de tipo (ex.: subiu para alto vindo de baixo). Reinicia.
        elseif ($prev !== null && $prev['alarm_type'] !== $currentType) {
            if ($prev['sms_sent_at'] !== null) {
                // Ja tinha enviado alarme para o tipo anterior; envia [OK] desse
                // tipo antes de arrancar o novo.
                $this->sendToRecipients(
                    $device,
                    (string) $prev['alarm_type'],
                    'OK',
                    $temperature,
                    (string) $prev['first_active_at']
                );
            }
            $this->replaceState($deviceId, $currentType, $temperature);
        }
        // Caso 4: primeiro registo fora do intervalo.
        else {
            $this->insertState($deviceId, $currentType, $temperature);
        }

        // Verifica se ja passou o tempo minimo para enviar o SMS.
        $state = $this->getState($deviceId);
        if ($state === null || $state['sms_sent_at'] !== null) {
            return;
        }

        $minMinutes = defined('SMS_ALARM_MIN_MINUTES') ? (int) SMS_ALARM_MIN_MINUTES : 60;
        if ($minMinutes < 0) { $minMinutes = 0; }

        $ageSeconds = time() - strtotime((string) $state['first_active_at']);
        if ($ageSeconds < ($minMinutes * 60)) {
            return;
        }

        $sentAny = $this->sendToRecipients(
            $device,
            (string) $state['alarm_type'],
            'ALARME',
            $temperature,
            (string) $state['first_active_at']
        );

        if ($sentAny) {
            $this->markSmsSent($deviceId);
        }
    }

    // ---- Estado -----------------------------------------------------------

    private function getState(int $deviceId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT device_id, alarm_type, first_active_at, last_active_at,
                    last_temperature, sms_sent_at
             FROM device_temp_alarm_state WHERE device_id = ? LIMIT 1'
        );
        $stmt->execute([$deviceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function insertState(int $deviceId, string $type, float $temperature): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO device_temp_alarm_state
                (device_id, alarm_type, first_active_at, last_active_at, last_temperature)
             VALUES (?, ?, NOW(), NOW(), ?)'
        );
        $stmt->execute([$deviceId, $type, $temperature]);
    }

    private function touchState(int $deviceId, float $temperature): void
    {
        $stmt = $this->db->prepare(
            'UPDATE device_temp_alarm_state
                SET last_active_at = NOW(), last_temperature = ?
              WHERE device_id = ?'
        );
        $stmt->execute([$temperature, $deviceId]);
    }

    private function replaceState(int $deviceId, string $type, float $temperature): void
    {
        $stmt = $this->db->prepare(
            'UPDATE device_temp_alarm_state
                SET alarm_type = ?, first_active_at = NOW(),
                    last_active_at = NOW(), last_temperature = ?, sms_sent_at = NULL
              WHERE device_id = ?'
        );
        $stmt->execute([$type, $temperature, $deviceId]);
    }

    private function markSmsSent(int $deviceId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE device_temp_alarm_state SET sms_sent_at = NOW() WHERE device_id = ?'
        );
        $stmt->execute([$deviceId]);
    }

    private function clearState(int $deviceId): void
    {
        $stmt = $this->db->prepare('DELETE FROM device_temp_alarm_state WHERE device_id = ?');
        $stmt->execute([$deviceId]);
    }

    // ---- Envio ------------------------------------------------------------

    /**
     * Envia SMS a todos os utilizadores que optaram por receber. Devolve true
     * se pelo menos um envio foi bem-sucedido.
     */
    private function sendToRecipients(
        array $device,
        string $alarmType,
        string $event,
        float $temperature,
        string $firstActiveAt
    ): bool {
        $recipients = $this->getRecipients();
        $message    = $this->buildMessage($device, $alarmType, $event, $temperature, $firstActiveAt);
        $deviceId   = (int) $device['id'];

        if (empty($recipients)) {
            $this->logSms(null, $deviceId, $alarmType, $event, '(sem destinatarios)', $message, 'skipped',
                'Nenhum utilizador com receive_sms_alarms=1');
            return false;
        }

        $sentAny = false;
        foreach ($recipients as $r) {
            $phone = trim((string) ($r['phone'] ?? ''));
            if ($phone === '') { continue; }

            if ($this->wasRecentlySent($phone, $deviceId, $alarmType, $event, 60)) {
                $this->logSms((int) $r['id'], $deviceId, $alarmType, $event, $phone, $message, 'skipped',
                    'Duplicado em 60s');
                continue;
            }

            $res = $this->getClient()->send($phone, $message);
            $status  = $res['ok'] ? 'sent' : 'failed';
            $respTxt = $res['ok']
                ? (is_string($res['response']) ? $res['response'] : json_encode($res['response']))
                : ($res['error'] ?? '');

            $this->logSms((int) $r['id'], $deviceId, $alarmType, $event, $phone, $message, $status, $respTxt);
            if ($res['ok']) {
                $sentAny = true;
            }
        }

        return $sentAny;
    }

    private function getRecipients(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT id, name, phone
                 FROM users
                 WHERE approved = 1
                   AND receive_sms_alarms = 1
                   AND phone IS NOT NULL AND phone <> ''"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function buildMessage(
        array $device,
        string $alarmType,
        string $event,
        float $temperature,
        string $firstActiveAt
    ): string {
        $deviceName = isset($device['name']) ? (string) $device['name'] : ('dispositivo_' . (int) $device['id']);
        $prefix     = $event === 'OK' ? '[OK]' : '[ALARME]';
        $ts         = date('d/m H:i');

        if ($event === 'OK') {
            $label = $alarmType === 'temp_high'
                ? 'Temperatura normalizada'
                : 'Temperatura normalizada';
            $body = sprintf('%s %s: %s (%.1fC) (%s)',
                $prefix, $deviceName, $label, $temperature, $ts);
        } else {
            $limit = $alarmType === 'temp_high'
                ? sprintf('max=%.1fC', (float) ($device['temp_max'] ?? TEMP_MAX))
                : sprintf('min=%.1fC', (float) ($device['temp_min'] ?? TEMP_MIN));
            $label = $alarmType === 'temp_high' ? 'Temperatura ALTA' : 'Temperatura BAIXA';
            $durationMin = max(1, (int) round((time() - strtotime($firstActiveAt)) / 60));
            $body = sprintf('%s %s: %s %.1fC (%s) ha %d min (%s)',
                $prefix, $deviceName, $label, $temperature, $limit, $durationMin, $ts);
        }

        $body = $this->stripAccents($body);
        if (strlen($body) > 160) {
            $body = substr($body, 0, 157) . '...';
        }
        return $body;
    }

    private function stripAccents(string $s): string
    {
        $map = [
            'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n',
            'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A',
            'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
            'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
            'Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
            'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
            'Ç'=>'C','Ñ'=>'N',
        ];
        return strtr($s, $map);
    }

    private function wasRecentlySent(
        string $phone,
        int $deviceId,
        string $alarmType,
        string $event,
        int $windowSeconds
    ): bool {
        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM sms_log
                  WHERE to_number = ?
                    AND device_id = ?
                    AND alarm_type = ?
                    AND event = ?
                    AND status = 'sent'
                    AND ts >= DATE_SUB(NOW(), INTERVAL ? SECOND)
                  LIMIT 1"
            );
            $stmt->execute([$phone, $deviceId, $alarmType, $event, $windowSeconds]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private function logSms(
        ?int $userId,
        int $deviceId,
        string $alarmType,
        string $event,
        string $toNumber,
        string $message,
        string $status,
        ?string $response
    ): void {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO sms_log
                    (device_id, alarm_type, event, to_number, message, status, response)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$deviceId, $alarmType, $event, $toNumber, $message, $status, $response]);
        } catch (Throwable $e) {
            $this->fileLog('log_sms falhou: ' . $e->getMessage());
        }

        $this->fileLog(sprintf(
            'device=%d type=%s event=%s to=%s status=%s',
            $deviceId, $alarmType, $event, $toNumber, $status
        ));
    }

    private function fileLog(string $msg): void
    {
        $dir = ROOT . '/storage/logs';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        @file_put_contents(
            $dir . '/sms_alarms.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    private function getClient(): TeltonikaSmsClient
    {
        if ($this->client === null) {
            $this->client = new TeltonikaSmsClient();
        }
        return $this->client;
    }

    private function tablesExist(): bool
    {
        static $checked = null;
        if ($checked !== null) {
            return $checked;
        }
        try {
            $this->db->query('SELECT 1 FROM device_temp_alarm_state LIMIT 1');
            $this->db->query('SELECT 1 FROM sms_log LIMIT 1');
            $checked = true;
        } catch (Throwable $e) {
            $checked = false;
        }
        return $checked;
    }
}
