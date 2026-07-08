<?php
/**
 * Cliente HTTP para envio de SMS atraves de um modem Teltonika (RutOS 7+).
 * Testado com TRB145. Adaptado do projeto work_log.
 *
 * Fluxo:
 *  1. Faz POST /api/login com utilizador/password -> recebe token JWT.
 *  2. Guarda o token num ficheiro JSON (expira em ~299 s).
 *  3. Envia SMS com POST /api/messages/actions/send + Authorization: Bearer.
 *  4. Se o modem devolver 401 (token expirado antes do esperado), refaz login uma vez.
 */

require_once __DIR__ . '/../../config/constants.php';

class TeltonikaSmsClient
{
    private string $baseUrl;
    private string $user;
    private string $pass;
    private int    $timeout;
    private bool   $verifySsl;
    private string $tokenFile;
    private int    $safetyWindow = 20;
    private string $lastError    = '';

    public function __construct()
    {
        $scheme = defined('MODEM_SCHEME') ? MODEM_SCHEME : 'http';
        $host   = defined('MODEM_HOST')   ? MODEM_HOST   : '192.168.2.1';

        $this->baseUrl   = rtrim($scheme . '://' . $host, '/');
        $this->user      = defined('MODEM_USER')       ? MODEM_USER       : 'admin';
        $this->pass      = defined('MODEM_PASS')       ? MODEM_PASS       : '';
        $this->timeout   = defined('MODEM_TIMEOUT')    ? (int) MODEM_TIMEOUT : 8;
        $this->verifySsl = defined('MODEM_VERIFY_SSL') ? (bool) MODEM_VERIFY_SSL : false;
        $this->tokenFile = defined('MODEM_TOKEN_FILE')
            ? MODEM_TOKEN_FILE
            : (ROOT . '/storage/sessions/modem_token.json');
    }

    /**
     * Envia um SMS. Devolve ['ok'=>bool, 'http_code'=>int, 'response'=>string|array, 'error'=>string].
     */
    public function send(string $number, string $message): array
    {
        if (!defined('SMS_ENABLED') || !SMS_ENABLED) {
            return ['ok' => false, 'http_code' => 0, 'response' => null, 'error' => 'SMS desativado (SMS_ENABLED=false)'];
        }
        if ($this->pass === '') {
            return ['ok' => false, 'http_code' => 0, 'response' => null, 'error' => 'MODEM_PASS nao configurada'];
        }

        $number = $this->normalizeNumber($number);
        if ($number === '') {
            return ['ok' => false, 'http_code' => 0, 'response' => null, 'error' => 'Numero de destino vazio ou invalido'];
        }

        $token = $this->getToken();
        if ($token === null) {
            $detail = $this->lastError !== '' ? (' - ' . $this->lastError) : '';
            return ['ok' => false, 'http_code' => 0, 'response' => null, 'error' => 'Falha a obter token do modem' . $detail];
        }

        $result = $this->doSend($number, $message, $token);

        // 401/403 -> token pode ter expirado antes do esperado. Refresh e retry 1x.
        if (!$result['ok'] && in_array($result['http_code'], [401, 403], true)) {
            @unlink($this->tokenFile);
            $token = $this->getToken();
            if ($token !== null) {
                $result = $this->doSend($number, $message, $token);
            }
        }

        return $result;
    }

    private function normalizeNumber(string $n): string
    {
        $n = trim($n);
        $hasPlus = (strlen($n) > 0 && $n[0] === '+');
        $digits  = preg_replace('/\D+/', '', $n);
        if ($digits === '') {
            return '';
        }
        return ($hasPlus ? '+' : '') . $digits;
    }

    private function getToken(): ?string
    {
        $cached = @file_get_contents($this->tokenFile);
        if ($cached !== false) {
            $data = json_decode($cached, true);
            if (is_array($data)
                && isset($data['token'], $data['expires_at'])
                && (int) $data['expires_at'] > (time() + $this->safetyWindow)) {
                return (string) $data['token'];
            }
        }
        return $this->login();
    }

    private function login(): ?string
    {
        $url = $this->baseUrl . '/api/login';
        $payload = json_encode(['username' => $this->user, 'password' => $this->pass]);

        $res = $this->httpRequest('POST', $url, $payload, null);
        if (!$res['ok']) {
            $this->lastError = 'login: ' . ($res['error'] ?: ('HTTP ' . $res['http_code']));
            return null;
        }

        $body = $res['response'];
        $token   = null;
        $expires = 299;
        if (is_array($body)) {
            if (isset($body['data']) && is_array($body['data'])) {
                if (isset($body['data']['token']))   { $token   = (string) $body['data']['token']; }
                if (isset($body['data']['expires'])) { $expires = (int)    $body['data']['expires']; }
            }
            if ($token === null && isset($body['token'])) {
                $token = (string) $body['token'];
            }
        }
        if ($token === null || $token === '') {
            $snippet = is_array($body) ? json_encode($body) : (string) $body;
            $this->lastError = 'login OK mas sem token na resposta: ' . substr($snippet, 0, 300);
            return null;
        }

        $dir = dirname($this->tokenFile);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        @file_put_contents(
            $this->tokenFile,
            json_encode(['token' => $token, 'expires_at' => time() + max(60, $expires)]),
            LOCK_EX
        );
        @chmod($this->tokenFile, 0640);

        return $token;
    }

    private function doSend(string $number, string $message, string $token): array
    {
        $url = $this->baseUrl . '/api/messages/actions/send';
        $data = ['number' => $number, 'message' => $message];
        if (defined('MODEM_ID') && MODEM_ID !== '') {
            $data['modem'] = MODEM_ID;
        }
        $payload = json_encode(['data' => $data]);

        return $this->httpRequest('POST', $url, $payload, $token);
    }

    private function httpRequest(string $method, string $url, ?string $body, ?string $token): array
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(4, $this->timeout),
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_NOSIGNAL       => 1,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw       = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'http_code' => 0, 'response' => null, 'error' => 'cURL: ' . $curlError];
        }

        $decoded = json_decode((string) $raw, true);
        $parsed  = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $raw;

        $ok = ($httpCode >= 200 && $httpCode < 300);
        if ($ok && is_array($parsed) && array_key_exists('success', $parsed) && $parsed['success'] === false) {
            $ok = false;
        }

        $error = '';
        if (!$ok) {
            if (is_array($parsed)) {
                $error = 'HTTP ' . $httpCode . ' - ' . json_encode($parsed);
            } else {
                $error = 'HTTP ' . $httpCode . ' - ' . substr((string) $parsed, 0, 400);
            }
        }

        return ['ok' => $ok, 'http_code' => $httpCode, 'response' => $parsed, 'error' => $error];
    }
}
