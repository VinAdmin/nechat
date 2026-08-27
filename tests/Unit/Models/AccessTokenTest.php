<?php

namespace Tests\Unit\Models;

use app\models\AccessToken;
use app\models\Users;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;

/**
 * Юнит-тесты AccessToken. Требуют реально настроенной БД (config/db.php) — модели
 * проекта не имеют слоя мокирования и подключаются к БД напрямую через wco\db\DB.
 * Создаваемые тестовые пользователи/токены не удаляются автоматически из БД пользователей
 * (нет DELETE-эндпойнта/метода), но токены каждый тест подчищает за собой через deleteToken().
 */
#[TestDox('Токены доступа (AccessToken)')]
final class AccessTokenTest extends TestCase
{
    private static string $login;
    private static string $userId;

    public static function setUpBeforeClass(): void
    {
        self::$login = 'phpunit_' . bin2hex(random_bytes(6));
        self::$userId = '@' . self::$login . ':' . \wco\kernel\WCO::$domain;

        // Users::registration() читает только php://input (недоступен в CLI), поэтому
        // заводим тестового пользователя напрямую через insert(), как это делает сам метод.
        $mUsers = new Users();
        $mUsers->insert([
            'user_id'  => self::$userId,
            'password' => password_hash('TestPass123!', PASSWORD_BCRYPT),
            'cdate'    => time(),
        ]);
    }

    #[TestDox('Созданный токен не содержит поля срока действия (exp)')]
    public function testCreateTokenDoesNotEmbedExpiration(): void
    {
        $mAccessToken = new AccessToken();
        $jwt = $mAccessToken->createToken(self::$userId);

        [, $payloadB64] = explode('.', $jwt);
        $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);

        self::assertSame(self::$userId, $payload['user_id']);
        self::assertArrayNotHasKey('exp', $payload, 'JWT не должен нести срок действия — сессия завершается только логаутом');

        $mAccessToken->deleteToken($jwt);
    }

    #[TestDox('Токен с корректной подписью проходит проверку')]
    public function testCheckTokenAcceptsValidSignature(): void
    {
        $mAccessToken = new AccessToken();
        $jwt = $mAccessToken->createToken(self::$userId);

        $checker = new AccessToken();
        self::assertTrue($checker->checkToken($jwt));
        self::assertSame(self::$userId, $checker->sender);

        $mAccessToken->deleteToken($jwt);
    }

    #[TestDox('Токен с испорченной подписью отклоняется')]
    public function testCheckTokenRejectsTamperedSignature(): void
    {
        $mAccessToken = new AccessToken();
        $jwt = $mAccessToken->createToken(self::$userId);
        $tampered = substr($jwt, 0, -1) . (substr($jwt, -1) === 'A' ? 'B' : 'A');

        $checker = new AccessToken();
        self::assertFalse($checker->checkToken($tampered));

        $mAccessToken->deleteToken($jwt);
    }

    #[TestDox('Токен с валидной подписью, но не выданный сервером (нет в БД), отклоняется')]
    public function testCheckTokenRejectsForgedTokenNotInDatabase(): void
    {
        // Валидная подпись, но такой токен никогда не выдавался (не сохранён в access_tokens)
        $forged = JWT::encode(['user_id' => self::$userId], SECRET_KEY, 'HS256');

        $checker = new AccessToken();
        self::assertFalse($checker->checkToken($forged));
    }

    #[TestDox('После удаления (логаута) токен перестаёт проходить проверку')]
    public function testDeleteTokenInvalidatesSubsequentChecks(): void
    {
        $mAccessToken = new AccessToken();
        $jwt = $mAccessToken->createToken(self::$userId);

        self::assertTrue($mAccessToken->deleteToken($jwt));

        $checker = new AccessToken();
        self::assertFalse($checker->checkToken($jwt), 'После deleteToken() токен должен становиться недействительным');
    }
}
