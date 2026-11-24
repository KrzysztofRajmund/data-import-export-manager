<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class BatchProcessingClient
{
    /** @return array{ok:bool,result:?array,reason:?string} */
    public function process(array $payload): array
    {
        try {
            $config = $this->loadConfig();
        } catch (RuntimeException $exception) {
            return ['ok' => false, 'result' => null, 'reason' => $exception->getMessage()];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'result' => null, 'reason' => 'PHP cURL extension is unavailable.'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (!is_string($body)) {
            return ['ok' => false, 'result' => null, 'reason' => 'Could not encode batch-processing payload.'];
        }

        $curl = curl_init($config['url']);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . $config['apiKey'],
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 500,
            CURLOPT_TIMEOUT_MS => $config['timeoutMs'],
        ]);

        $response = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false || $error !== '') {
            return ['ok' => false, 'result' => null, 'reason' => 'Node.js service request failed: ' . ($error !== '' ? $error : 'no response')];
        }
        if ($status !== 200) {
            return ['ok' => false, 'result' => null, 'reason' => 'Node.js service returned HTTP ' . $status . '.'];
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true || !is_array($decoded['result'] ?? null)) {
            return ['ok' => false, 'result' => null, 'reason' => 'Node.js service returned an invalid response.'];
        }

        return ['ok' => true, 'result' => $decoded['result'], 'reason' => null];
    }

    private function loadConfig(): array
    {
        $fileConfig = [];
        $path = __DIR__ . '/../../config/config.local.php';
        if (is_file($path)) {
            $local = require $path;
            $fileConfig = is_array($local['worker'] ?? null) ? $local['worker'] : [];
        }

        $url = (string) ($fileConfig['url'] ?? (getenv('WORKER_URL') ?: 'http://127.0.0.1:3001/process-batch'));
        $apiKey = (string) ($fileConfig['apiKey'] ?? (getenv('WORKER_API_KEY') ?: 'change-this-local-worker-secret'));
        $timeoutMs = (int) ($fileConfig['timeoutMs'] ?? (getenv('WORKER_TIMEOUT_MS') ?: 2000));

        if ($apiKey === '') throw new RuntimeException('Worker API key is not configured.');
        return ['url' => $url, 'apiKey' => $apiKey, 'timeoutMs' => max(100, $timeoutMs)];
    }
}
