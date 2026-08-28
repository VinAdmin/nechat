<?php

namespace Tests\Functional;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\SkippedTestSuiteError;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\ApiClient;

/**
 * HTTP-тест изменения отображаемого имени пользователя (users.name) через
 * /api/v1/profile/.
 *
 * Требует запущенный сервер (по умолчанию https://chat.loc, переопределяется
 * API_BASE_URL). Если сервер недоступен — вся сюита skipped.
 *
 * Запуск: composer test:functional
 */
#[TestDox('API отображаемого имени пользователя (HTTP)')]
final class ProfileNameApiTest extends TestCase
{
    private static string $baseUrl;
    private static string $host;
    private static string $token;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = getenv('API_BASE_URL') ?: 'https://chat.loc';
        self::$host = preg_replace('#/.*$#', '', preg_replace('#^https?://#', '', self::$baseUrl));

        $anon = new ApiClient(self::$baseUrl);
        try {
            $ping = $anon->get('/api/v1/version/');
        } catch (\Throwable $e) {
            throw new SkippedTestSuiteError('API недоступен: ' . $e->getMessage());
        }
        if ($ping->code !== 200) {
            throw new SkippedTestSuiteError('GET /api/v1/version/ вернул HTTP ' . $ping->code);
        }

        $login = 'name_api_' . time() . random_int(1000, 9999);
        $anon->postJson('/api/v1/registration/', ['login' => $login, 'password' => 'TestPass123!']);
        $resp = $anon->postJson('/api/v1/authorization/', ['login' => $login, 'password' => 'TestPass123!']);
        self::assertSame(200, $resp->code, 'авторизация: ' . $resp->rawBody);
        self::$token = $resp->json['token'] ?? '';
        self::assertNotSame('', self::$token, 'нет токена: ' . $resp->rawBody);
    }

    private function client(): ApiClient
    {
        return (new ApiClient(self::$baseUrl))->withToken(self::$token);
    }

    #[TestDox('У нового пользователя name пустое')]
    public function testNewUserHasEmptyName(): void
    {
        $res = $this->client()->get('/api/v1/profile/')->json;
        self::assertSame('', $res['name'] ?? null, json_encode($res));
    }

    #[TestDox('POST name сохраняет отображаемое имя, GET его возвращает')]
    public function testPostNamePersists(): void
    {
        $ok = $this->client()->postJson('/api/v1/profile/', ['name' => 'Пётр Тестовый'])->json;
        self::assertSame('ok', $ok['status'] ?? null, json_encode($ok));

        $res = $this->client()->get('/api/v1/profile/')->json;
        self::assertSame('Пётр Тестовый', $res['name'] ?? null, json_encode($res));
    }

    #[TestDox('Пустой name очищает имя (возврат к логину в интерфейсе)')]
    public function testEmptyNameClears(): void
    {
        $this->client()->postJson('/api/v1/profile/', ['name' => 'Временное']);
        $ok = $this->client()->postJson('/api/v1/profile/', ['name' => ''])->json;
        self::assertSame('ok', $ok['status'] ?? null, json_encode($ok));

        $res = $this->client()->get('/api/v1/profile/')->json;
        self::assertSame('', $res['name'] ?? null, json_encode($res));
    }

    #[TestDox('HTML-теги в name вырезаются')]
    public function testNameIsSanitized(): void
    {
        $this->client()->postJson('/api/v1/profile/', ['name' => '<script>alert(1)</script>Иван']);
        $res = $this->client()->get('/api/v1/profile/')->json;
        self::assertStringNotContainsString('<script>', $res['name'] ?? '');
        self::assertStringContainsString('Иван', $res['name'] ?? '');
    }
}
