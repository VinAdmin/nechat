<?php

namespace Tests\Unit\Models;

use app\models\AccessToken;
use app\models\Users;
use app\models\Rooms;
use app\models\Events;
use app\models\EventJson;
use app\models\RoomMemberships;
use app\models\UserPresence;
use app\models\TypingIndicator;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;

/**
 * Юнит-тесты AccessToken. Требуют реально настроенной БД (config/db.php) — модели
 * проекта не имеют слоя мокирования и подключаются к БД напрямую через wco\db\DB.
 * Общий тестовый пользователь из setUpBeforeClass() не удаляется автоматически, но
 * токены каждый тест подчищает за собой через deleteToken(). Тест на
 * Users::deleteAccount() заводит собственных пользователей с уникальным суффиксом
 * и убирает за собой всё, что можно (кроме уже удалённой строки самого пользователя).
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

    #[TestDox('Users::deleteAccount() отзывает ВСЕ токены пользователя и чистит его данные, не трогая комнаты/сообщения и чужие записи')]
    public function testDeleteAccountRevokesAllTokensAndPurgesUserData(): void
    {
        $suffix   = bin2hex(random_bytes(6));
        $victim   = '@phpunit_del_' . $suffix . ':' . \wco\kernel\WCO::$domain;
        $bystander = '@phpunit_del_bystander_' . $suffix . ':' . \wco\kernel\WCO::$domain;
        $roomId   = '!phpunit_del_room_' . $suffix . ':' . \wco\kernel\WCO::$domain;
        $bodyMarker = 'DEL_KEEP_MSG_' . $suffix;

        $mUsers = new Users();
        $mUsers->insert(['user_id' => $victim,    'password' => password_hash('TestPass123!', PASSWORD_BCRYPT), 'cdate' => time()]);
        $mUsers->insert(['user_id' => $bystander, 'password' => password_hash('TestPass123!', PASSWORD_BCRYPT), 'cdate' => time()]);

        // Две активные сессии удаляемого пользователя.
        $mAccessToken = new AccessToken();
        $token1 = $mAccessToken->createToken($victim);
        $token2 = $mAccessToken->createToken($victim);

        // Комната, созданная удаляемым пользователем, + его сообщение в ней.
        (new Rooms())->insert([
            'room_id'   => $roomId,
            'name'      => 'Del room ' . $suffix,
            'topic'     => '',
            'join_rule' => 'public',
            'creator'   => $victim,
            'cdate'     => time(),
        ]);

        $mEvents = new Events();
        $memberEventId = $mEvents->addEvent(['type' => 'm.room.member', 'room_id' => $roomId, 'sender' => $victim]);
        (new RoomMemberships())->addUser([
            'event_id'   => $memberEventId,
            'user_id'    => $victim,
            'sender'     => $victim,
            'room_id'    => $roomId,
            'membership' => 'join',
        ]);
        (new RoomMemberships())->addUser([
            'event_id'   => $mEvents->addEvent(['type' => 'm.room.member', 'room_id' => $roomId, 'sender' => $bystander]),
            'user_id'    => $bystander,
            'sender'     => $bystander,
            'room_id'    => $roomId,
            'membership' => 'join',
        ]);

        $msgEventId = $mEvents->addEvent(['type' => 'm.room.message', 'room_id' => $roomId, 'sender' => $victim]);
        (new EventJson())->add([
            'event_id' => $msgEventId,
            'room_id'  => $roomId,
            'json'     => json_encode([
                'event_id' => $msgEventId,
                'type'     => 'm.room.message',
                'room_id'  => $roomId,
                'sender'   => $victim,
                'content'  => ['body' => $bodyMarker, 'msgtype' => 'm.text'],
            ]),
        ]);

        // Presence и typing — у обоих пользователей.
        (new UserPresence())->heartbeat($victim);
        (new UserPresence())->heartbeat($bystander);
        (new TypingIndicator())->setTyping($victim, $roomId);
        (new TypingIndicator())->setTyping($bystander, $roomId);

        // --- Действие ---
        $mUsers->deleteAccount($victim);

        // --- Удалено: все токены сессий ---
        self::assertFalse((new AccessToken())->checkToken($token1), 'Первый токен должен быть отозван');
        self::assertFalse((new AccessToken())->checkToken($token2), 'Второй токен должен быть отозван — удаляются ВСЕ сессии, а не только текущая');

        // --- Удалено: строка пользователя и его записи в служебных таблицах ---
        self::assertSame([], (new Users())->getUserById($victim), 'Строка пользователя должна быть удалена');
        self::assertSame([], (new RoomMemberships())->getRoomMember($roomId, $victim), 'Членство удаляемого пользователя должно быть удалено');
        self::assertSame(0, (int) (new UserPresence())->getPresence($victim)['last_active'], 'Запись presence должна быть удалена');
        self::assertNotContains($victim, (new TypingIndicator())->getTypingUsers($roomId, ''), 'Индикатор набора удаляемого пользователя должен быть удалён');

        // --- НЕ тронуто: чужие записи (значит удаление шло по WHERE user_id, а не по всей таблице) ---
        self::assertNotSame([], (new Users())->getUserById($bystander), 'Другой пользователь не должен быть затронут');
        self::assertNotSame([], (new RoomMemberships())->getRoomMember($roomId, $bystander), 'Членство другого пользователя не должно быть затронуто');
        self::assertGreaterThan(0, (int) (new UserPresence())->getPresence($bystander)['last_active'], 'Presence другого пользователя не должен быть затронут');
        self::assertContains($bystander, (new TypingIndicator())->getTypingUsers($roomId, ''), 'Индикатор набора другого пользователя не должен быть затронут');

        // --- НЕ тронуто: комната и сообщения удалённого пользователя ---
        $room = (new Rooms())->getRoomId($roomId);
        self::assertSame($roomId, $room['room_id'] ?? null, 'Комната должна остаться');
        self::assertSame($victim, $room['creator'] ?? null, 'creator остаётся прежним — комната просто без действующего владельца');

        $mKeptEvents = new Events();
        $mKeptEvents->select()->from()->where('event_id = :event_id');
        $keptEvent = $mKeptEvents->fetch(['event_id' => $msgEventId]);
        self::assertSame($msgEventId, $keptEvent['event_id'] ?? null, 'Событие-сообщение должно остаться');

        $mKeptJson = new EventJson();
        $mKeptJson->select()->from()->where('event_id = :event_id');
        $keptJson = $mKeptJson->fetch(['event_id' => $msgEventId]);
        self::assertStringContainsString($bodyMarker, $keptJson['json'] ?? '', 'Тело сообщения должно остаться');

        // --- Уборка фикстур ---
        (new EventJson())->delete('room_id = :room_id')->execute([':room_id' => $roomId]);
        (new Events())->delete('room_id = :room_id')->execute([':room_id' => $roomId]);
        (new RoomMemberships())->delete('room_id = :room_id')->execute([':room_id' => $roomId]);
        (new TypingIndicator())->delete('room_id = :room_id')->execute([':room_id' => $roomId]);
        (new Rooms())->delete('room_id = :room_id')->execute([':room_id' => $roomId]);
        (new UserPresence())->delete('user_id = :user_id')->execute([':user_id' => $bystander]);
        (new Users())->delete('user_id = :user_id')->execute([':user_id' => $bystander]);
    }
}
