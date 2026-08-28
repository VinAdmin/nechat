<?php

namespace Tests\Functional\Support;

/**
 * Минимальный HTTP-клиент на cURL для функциональных тестов API.
 *
 * В dev-окружении (debug=true) к каждому ответу приклеивается Tracy debug bar, а иногда
 * перед JSON просачивается PHP-notice — поэтому JSON вытаскиваем регуляркой по всему телу,
 * а не полагаемся на то, что тело ответа целиком валидный JSON.
 */
final class ApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $token = null,
        private readonly bool $insecure = true,
    ) {
    }

    public function withToken(?string $token): self
    {
        return new self($this->baseUrl, $token, $this->insecure);
    }

    public function get(string $path): ApiResponse
    {
        return $this->request('GET', $path);
    }

    public function postJson(string $path, array $body): ApiResponse
    {
        return $this->request('POST', $path, jsonBody: $body);
    }

    /**
     * @param array<string,string> $fields
     * @param array<string,string> $files  field => local file path
     * @param array<string,string> $fileNames  field => имя файла, под которым его увидит сервер (basename)
     * @param array<string,string> $fileMimeTypes  field => Content-Type части
     */
    public function postForm(string $path, array $fields = [], array $files = [], array $fileNames = [], array $fileMimeTypes = []): ApiResponse
    {
        $payload = $fields;
        foreach ($files as $field => $localPath) {
            $name = $fileNames[$field] ?? basename($localPath);
            $mime = $fileMimeTypes[$field] ?? 'application/octet-stream';
            $payload[$field] = new \CURLFile($localPath, $mime, $name);
        }

        return $this->request('POST', $path, formBody: $payload);
    }

    public function head(string $path): ApiResponse
    {
        return $this->request('HEAD', $path);
    }

    private function request(string $method, string $path, ?array $jsonBody = null, ?array $formBody = null): ApiResponse
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = [];
        if ($this->token !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $options = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_SSL_VERIFYPEER => !$this->insecure,
            CURLOPT_SSL_VERIFYHOST => $this->insecure ? 0 : 2,
            CURLOPT_TIMEOUT        => 15,
        ];

        if ($method === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        }

        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($jsonBody);
        } elseif ($formBody !== null) {
            $options[CURLOPT_POSTFIELDS] = $formBody; // multipart, cURL сам выставит Content-Type
        }

        $options[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $options);

        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            throw new \RuntimeException("cURL error for $method $path: $error");
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        // curl_close() с PHP 8.0 не нужен (handle — обычный объект, уничтожается GC);
        // в PHP 8.5 вызов помечен deprecated.

        $rawHeaders = substr($raw, 0, $headerSize);
        $rawBody = substr($raw, $headerSize);

        return new ApiResponse($code, $rawBody, $rawHeaders);
    }
}
