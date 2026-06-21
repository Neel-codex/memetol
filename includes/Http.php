<?php
/**
 * Http - tiny cURL JSON client for external APIs.
 */
class Http
{
    public static function getJson(string $url, array $headers = [], int $timeout = 12): ?array
    {
        $raw = self::get($url, $headers, $timeout);
        if ($raw === null) {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    public static function get(string $url, array $headers = [], int $timeout = 12): ?string
    {
        if (!function_exists('curl_init')) {
            // Fallback to file_get_contents
            $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'header' => implode("\r\n", $headers)]]);
            $res = @file_get_contents($url, false, $ctx);
            return $res === false ? null : $res;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT      => 'MemeRadarAI/1.0',
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($res === false || $code >= 400) {
            return null;
        }
        return (string) $res;
    }

    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 12): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string) $res, true);
        return is_array($data) ? $data : null;
    }
}
