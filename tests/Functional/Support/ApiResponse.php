<?php

namespace Tests\Functional\Support;

final class ApiResponse
{
    public readonly ?array $json;

    public function __construct(
        public readonly int $code,
        public readonly string $rawBody,
        public readonly string $rawHeaders,
    ) {
        // Тело ответа не обязательно "чистый" JSON: dev-окружение (debug=true) может
        // приклеить Tracy debug bar после JSON, а PHP notice/warning — вывести перед ним.
        // Поэтому ищем первый JSON-объект/массив в теле, а не парсим его целиком.
        if (preg_match('/^(\{.*\}|\[.*\])\s*$/m', $rawBody, $m)) {
            $decoded = json_decode($m[1], true);
            $this->json = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        } else {
            $this->json = null;
        }
    }

    public function header(string $name): ?string
    {
        if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.+?)\r?$/mi', $this->rawHeaders, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    public function error(): ?string
    {
        return $this->json['error'] ?? null;
    }
}
