<?php

namespace Tests\Functional;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\SkippedTestSuiteError;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\ApiClient;

/**
 * HTTP-тест прав доступа комнаты (power levels).
 *
 * Требует реально запущенный сервер (по умолчанию https://chat.loc,
 * переопределяется API_BASE_URL). Если сервер недоступен — вся сюита skipped,
 * как в SecurityRegressionTest.
 *
 * Запуск: composer test:functional
 */
#[TestDox('API прав доступа комнаты (HTTP)')]
final class PowerLevelsApiTest extends TestCase
{
    private static string $baseUrl;
    private static string $host;
    private static string $runId;
    private static string $tokenOwner;
    private static string $tokenMember;
    private static string $userMember;
    private static string $roomId;

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

        self::$runId = (string) time() . random_int(1000, 9999);

        self::$tokenOwner = self::registerAndLogin($anon, 'pl_owner_' . self::$runId);
        self::$tokenMember = self::registerAndLogin($anon, 'pl_member_' . self::$runId);
        self::$userMember = '@pl_member_' . self::$runId . ':' . self::$host;

        $owner = $anon->withToken(self::$tokenOwner);
        $roomName = 'PL Room ' . self::$runId;
        $resp = $owner->postJson('/api/v1/createRoom/', ['name' => $roomName]);
        self::assertSame(200, $resp->code, 'createRoom: ' . $resp->rawBody);

        self::$roomId = '';
        foreach ($owner->get('/api/v1/joined_rooms/')->json ?? [] as $room) {
            if (($room['name'] ?? null) === $roomName) {
                self::$roomId = $room['room_id'];
            }
        }
        self::assertNotSame('', self::$roomId, 'комната не найдена в joined_rooms');

        // member вступает в публичную комнату
        $anon->withToken(self::$tokenMember)->postJson('/api/v1/joinRoom/', ['room_id' => self::$roomId]);
    }

    private static function registerAndLogin(ApiClient $anon, string $login): string
    {
        $anon->postJson('/api/v1/registration/', ['login' => $login, 'password' => 'TestPass123!']);
        $resp = $anon->postJson('/api/v1/authorization/', ['login' => $login, 'password' => 'TestPass123!']);
        self::assertSame(200, $resp->code, "авторизация $login: " . $resp->rawBody);
        $token = $resp->json['token'] ?? null;
        self::assertIsString($token, "ответ авторизации $login без токена: " . $resp->rawBody);
        return $token;
    }

    private function owner(): ApiClient
    {
        return (new ApiClient(self::$baseUrl))->withToken(self::$tokenOwner);
    }

    private function member(): ApiClient
    {
        return (new ApiClient(self::$baseUrl))->withToken(self::$tokenMember);
    }

    #[TestDox('createRoom создал стартовое событие прав: владелец = 100')]
    public function testInitialPowerLevels(): void
    {
        $res = $this->owner()->get('/api/v1/rooms/' . self::$roomId . '/power_levels/')->json;
        self::assertSame(100, $res['my_level'] ?? null, json_encode($res));
        self::assertSame(50, $res['power_levels']['ban'] ?? null);
    }

    #[TestDox('Обычный участник не может менять права: Insufficient power level')]
    public function testMemberCannotChangePowerLevels(): void
    {
        $res = $this->member()->postJson('/api/v1/rooms/' . self::$roomId . '/power_levels/', [
            'thresholds' => ['ban' => 40],
        ])->json;
        self::assertSame('Insufficient power level', $res['error'] ?? null, json_encode($res));
    }

    #[TestDox('Владелец назначает участнику уровень 50 — видно в GET и в members')]
    public function testOwnerAssignsLevel(): void
    {
        $ok = $this->owner()->postJson('/api/v1/rooms/' . self::$roomId . '/power_levels/', [
            'user_id' => self::$userMember, 'level' => 50,
        ])->json;
        self::assertSame('ok', $ok['status'] ?? null, json_encode($ok));

        $res = $this->owner()->get('/api/v1/rooms/' . self::$roomId . '/power_levels/')->json;
        self::assertSame(50, $res['power_levels']['users'][self::$userMember] ?? null, json_encode($res));

        $members = $this->owner()->get('/api/v1/rooms/' . self::$roomId . '/members/')->json;
        $row = null;
        foreach ($members ?? [] as $m) {
            if (($m['user_id'] ?? '') === self::$userMember) {
                $row = $m;
            }
        }
        self::assertSame(50, $row['power_level'] ?? null, json_encode($members));
    }

    #[TestDox('Владелец не может назначить уровень выше своего (100)')]
    public function testOwnerCannotAssignAboveOwn(): void
    {
        $res = $this->owner()->postJson('/api/v1/rooms/' . self::$roomId . '/power_levels/', [
            'user_id' => self::$userMember, 'level' => 150,
        ])->json;
        self::assertArrayHasKey('error', $res, json_encode($res));
    }

    #[TestDox('Announcement: при events_default=100 участник (50) не может писать, владелец может')]
    public function testAnnouncementMode(): void
    {
        $set = $this->owner()->postJson('/api/v1/rooms/' . self::$roomId . '/power_levels/', [
            'thresholds' => ['events_default' => 100],
        ])->json;
        self::assertSame('ok', $set['status'] ?? null, json_encode($set));

        $blocked = $this->member()->postJson('/api/v1/rooms/', [
            'room_id' => self::$roomId, 'msgtype' => 'm.text', 'body' => 'hi',
        ])->json;
        self::assertSame('Sending a message is prohibited', $blocked['error'] ?? null, json_encode($blocked));

        $allowed = $this->owner()->postJson('/api/v1/rooms/', [
            'room_id' => self::$roomId, 'msgtype' => 'm.text', 'body' => 'announce',
        ])->json;
        self::assertSame('ok', $allowed['status'] ?? null, json_encode($allowed));
    }
}
