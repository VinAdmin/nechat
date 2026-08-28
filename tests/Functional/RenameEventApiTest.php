<?php

namespace Tests\Functional;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\SkippedTestSuiteError;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\ApiClient;

/**
 * HTTP-тест: смена отображаемого имени порождает системное событие m.room.member
 * (content.rename) во всех join-комнатах пользователя, и другие участники видят
 * его в /api/v1/sync/.
 *
 * Требует запущенный сервер (API_BASE_URL, по умолчанию https://chat.loc).
 */
#[TestDox('Событие смены имени в комнате (HTTP)')]
final class RenameEventApiTest extends TestCase
{
    private static string $baseUrl;
    private static string $host;
    private static string $tokenA;
    private static string $tokenB;
    private static string $userA;
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

        $run = time() . random_int(1000, 9999);
        $loginA = 'rename_a_' . $run;
        $loginB = 'rename_b_' . $run;
        self::$userA = '@' . $loginA . ':' . self::$host;

        self::$tokenA = self::reg($anon, $loginA);
        self::$tokenB = self::reg($anon, $loginB);

        $a = $anon->withToken(self::$tokenA);
        $roomName = 'Rename Room ' . $run;
        $a->postJson('/api/v1/createRoom/', ['name' => $roomName]);
        foreach ($a->get('/api/v1/joined_rooms/')->json ?? [] as $room) {
            if (($room['name'] ?? null) === $roomName) {
                self::$roomId = $room['room_id'];
            }
        }
        self::assertNotEmpty(self::$roomId, 'комната не найдена');

        $anon->withToken(self::$tokenB)->postJson('/api/v1/joinRoom/', ['room_id' => self::$roomId]);
    }

    private static function reg(ApiClient $anon, string $login): string
    {
        $anon->postJson('/api/v1/registration/', ['login' => $login, 'password' => 'TestPass123!']);
        $resp = $anon->postJson('/api/v1/authorization/', ['login' => $login, 'password' => 'TestPass123!']);
        return $resp->json['token'] ?? '';
    }

    /** @return array<int,array> content'ы событий rename, которые видит участник B */
    private function renameEventsSeenByB(): array
    {
        $sync = (new ApiClient(self::$baseUrl))->withToken(self::$tokenB)->get('/api/v1/sync/')->json;
        $out = [];
        foreach ($sync['rooms']['join'][self::$roomId]['events'] ?? [] as $e) {
            $c = $e['json']['content'] ?? [];
            if (($c['rename'] ?? false) === true) {
                $out[] = $c;
            }
        }
        return $out;
    }

    #[TestDox('Смена имени участником A видна участнику B как событие rename')]
    public function testRenameVisibleToOtherMember(): void
    {
        $a = (new ApiClient(self::$baseUrl))->withToken(self::$tokenA);
        $a->postJson('/api/v1/profile/', ['name' => 'Первое Имя']);
        $a->postJson('/api/v1/profile/', ['name' => 'Второе Имя']);

        $events = $this->renameEventsSeenByB();
        self::assertGreaterThanOrEqual(2, count($events));

        $last = end($events);
        self::assertSame('Первое Имя', $last['prev_displayname']);
        self::assertSame('Второе Имя', $last['displayname']);
        self::assertFalse($last['cleared']);
    }

    #[TestDox('Очистка имени порождает событие cleared = true')]
    public function testClearingNameEmitsClearedEvent(): void
    {
        (new ApiClient(self::$baseUrl))->withToken(self::$tokenA)
            ->postJson('/api/v1/profile/', ['name' => '']);

        $events = $this->renameEventsSeenByB();
        self::assertNotEmpty($events);
        $last = end($events);
        self::assertTrue($last['cleared'] ?? false);
        self::assertSame('Второе Имя', $last['prev_displayname'] ?? null);
    }
}
