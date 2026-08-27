<?php

namespace Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\SkippedTestSuiteError;
use PHPUnit\Framework\TestCase;
use Tests\Functional\Support\ApiClient;
use Tests\Functional\Support\ApiResponse;

/**
 * Функциональный (HTTP) регресс-тест на исправления безопасности из
 * security-fixes-report.md — PHPUnit-версия tests/../claude/api-security-tests.sh.
 *
 * Требует реально запущенный и доступный сервер (по умолчанию https://chat.loc,
 * переопределяется переменной окружения API_BASE_URL). Тест регистрирует настоящих
 * тестовых пользователей и создаёт тестовые комнаты в БД, за которой стоит сервер —
 * удалить их после прогона нечем (в API нет метода удаления пользователя/комнаты).
 *
 * Запуск: composer test:functional
 *         API_BASE_URL=https://chat.loc vendor/bin/phpunit --testsuite Functional
 */
#[TestDox('Регресс безопасности API (HTTP)')]
final class SecurityRegressionTest extends TestCase
{
    private static string $baseUrl;
    private static string $host;
    private static string $runId;

    private static string $tokenA;
    private static string $tokenB;
    private static string $tokenC;
    private static string $tokenD;

    private static string $roomId;
    private static string $userA;
    private static string $userC;
    private static string $userD;

    private static ?string $tmpImage = null;
    private static ?string $tmpHtml = null;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = getenv('API_BASE_URL') ?: 'https://chat.loc';
        self::$host = preg_replace('#/.*$#', '', preg_replace('#^https?://#', '', self::$baseUrl));

        $anon = new ApiClient(self::$baseUrl);
        try {
            $ping = $anon->get('/api/v1/version/');
        } catch (\Throwable $e) {
            throw new SkippedTestSuiteError('API недоступен по адресу ' . self::$baseUrl . ': ' . $e->getMessage());
        }
        if ($ping->code !== 200) {
            throw new SkippedTestSuiteError('GET /api/v1/version/ вернул HTTP ' . $ping->code . ' — сервер недоступен или неисправен, тесты пропущены.');
        }

        self::$runId = (string) time() . random_int(1000, 9999);

        self::$tokenA = self::registerAndLogin($anon, 'qa_a_' . self::$runId);
        self::$tokenB = self::registerAndLogin($anon, 'qa_b_' . self::$runId);
        self::$tokenC = self::registerAndLogin($anon, 'qa_c_' . self::$runId);
        self::$tokenD = self::registerAndLogin($anon, 'qa_d_' . self::$runId);

        self::$userA = '@qa_a_' . self::$runId . ':' . self::$host;
        self::$userC = '@qa_c_' . self::$runId . ':' . self::$host;
        self::$userD = '@qa_d_' . self::$runId . ':' . self::$host;

        $clientA = $anon->withToken(self::$tokenA);
        $clientB = $anon->withToken(self::$tokenB);
        $clientC = $anon->withToken(self::$tokenC);

        // B и C должны состоять хоть в какой-то комнате, иначе Rooms::accessRoom() в
        // actionRooms() отсекает их ДО проверяемой нами логики другим текстом ошибки
        // ("Messages are not allowed in this room.") — это отдельный, более старый и
        // низкоприоритетный баг (room-agnostic gate), не то, что мы здесь тестируем.
        self::createRoom($clientB, 'QA Decoy B ' . self::$runId);
        self::createRoom($clientC, 'QA Decoy C ' . self::$runId);

        $roomName = 'QA Room ' . self::$runId;
        self::createRoom($clientA, $roomName);
        self::$roomId = self::findRoomIdByName($clientA, $roomName);

        $clientA->postJson('/api/v1/rooms/' . self::$roomId . '/invite/', ['user_id' => self::$userC]);
        $clientA->postJson('/api/v1/rooms/' . self::$roomId . '/ban/', ['user_id' => self::$userC]);
        $clientA->postJson('/api/v1/rooms/' . self::$roomId . '/invite/', ['user_id' => self::$userD]);

        self::$tmpImage = tempnam(sys_get_temp_dir(), 'qa_img_') . '.png';
        file_put_contents(
            self::$tmpImage,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
        );

        self::$tmpHtml = tempnam(sys_get_temp_dir(), 'qa_evil_') . '.html';
        file_put_contents(self::$tmpHtml, '<script>document.title="XSS-EXECUTED"</script>');
    }

    public static function tearDownAfterClass(): void
    {
        foreach ([self::$tmpImage, self::$tmpHtml] as $f) {
            if ($f !== null && is_file($f)) {
                @unlink($f);
            }
        }
    }

    private static function registerAndLogin(ApiClient $anon, string $login): string
    {
        $anon->postJson('/api/v1/registration/', ['login' => $login, 'password' => 'TestPass123!']);
        $resp = $anon->postJson('/api/v1/authorization/', ['login' => $login, 'password' => 'TestPass123!']);

        self::assertSame(200, $resp->code, "Не удалось авторизовать $login: " . $resp->rawBody);
        $token = $resp->json['token'] ?? null;
        self::assertIsString($token, "Ответ авторизации $login без токена: " . $resp->rawBody);

        return $token;
    }

    private static function createRoom(ApiClient $client, string $name): void
    {
        $resp = $client->postJson('/api/v1/createRoom/', ['name' => $name]);
        self::assertSame(200, $resp->code, "Не удалось создать комнату '$name': " . $resp->rawBody);
    }

    private static function findRoomIdByName(ApiClient $client, string $name): string
    {
        $resp = $client->get('/api/v1/joined_rooms/');
        self::assertSame(200, $resp->code);

        foreach ($resp->json ?? [] as $room) {
            if (($room['name'] ?? null) === $name) {
                return $room['room_id'];
            }
        }

        self::fail("Комната '$name' не найдена в joined_rooms");
    }

    private function client(string $token): ApiClient
    {
        return (new ApiClient(self::$baseUrl))->withToken($token);
    }

    // ---------- Блок 1: членство при отправке сообщений/файлов ----------

    #[Test]
    #[TestDox('Участник комнаты может отправить текстовое сообщение')]
    public function memberCanSendTextMessage(): void
    {
        $resp = $this->client(self::$tokenA)->postJson('/api/v1/rooms/', [
            'room_id' => self::$roomId,
            'msgtype' => 'm.text',
            'body'    => 'hello from A',
        ]);

        self::assertSame('ok', $resp->json['status'] ?? null, $resp->rawBody);
    }

    #[Test]
    #[TestDox('Не участник не может отправить сообщение в чужую комнату')]
    public function nonMemberCannotSendTextMessage(): void
    {
        $resp = $this->client(self::$tokenB)->postJson('/api/v1/rooms/', [
            'room_id' => self::$roomId,
            'msgtype' => 'm.text',
            'body'    => 'hello from B',
        ]);

        self::assertSame('Sending a message is prohibited', $resp->error());
    }

    #[Test]
    #[TestDox('Не участник не может загрузить файл в чужую комнату')]
    public function nonMemberCannotUploadFile(): void
    {
        $resp = $this->client(self::$tokenB)->postForm('/api/v1/rooms/', [
            'room_id' => self::$roomId,
            'msgtype' => 'm.file',
        ], ['file' => self::$tmpImage], [], ['file' => 'image/png']);

        self::assertSame('Sending a message is prohibited', $resp->error());
    }

    #[Test]
    #[TestDox('Участник комнаты может загрузить файл')]
    public function memberCanUploadFile(): void
    {
        $marker = 'QAIMG_' . self::$runId;
        $resp = $this->client(self::$tokenA)->postForm('/api/v1/rooms/', [
            'room_id' => self::$roomId,
            'msgtype' => 'm.file',
            'body'    => $marker,
        ], ['file' => self::$tmpImage], [], ['file' => 'image/png']);

        self::assertSame('ok', $resp->json['status'] ?? null, $resp->rawBody);
    }

    #[Test]
    #[TestDox('Забаненный пользователь не может отправить сообщение')]
    public function bannedUserCannotSendMessage(): void
    {
        $resp = $this->client(self::$tokenC)->postJson('/api/v1/rooms/', [
            'room_id' => self::$roomId,
            'msgtype' => 'm.text',
            'body'    => 'hi from banned C',
        ]);

        self::assertSame('Sending a message is prohibited', $resp->error());
    }

    #[Test]
    #[TestDox('Приглашённый, но не вступивший пользователь не может отправить сообщение')]
    public function invitedButNotJoinedUserCannotSendMessage(): void
    {
        $resp = $this->client(self::$tokenD)->postJson('/api/v1/rooms/', [
            'room_id' => self::$roomId,
            'msgtype' => 'm.text',
            'body'    => 'hi from invited D',
        ]);

        self::assertSame('Sending a message is prohibited', $resp->error());
    }

    // ---------- Блок 2: Content-Disposition при отдаче файлов (XSS) ----------

    #[Test]
    #[TestDox('Вредоносный html-файл отдаётся как вложение, а не открывается в браузере (защита от XSS)')]
    public function maliciousHtmlFileIsServedAsAttachmentNotInline(): void
    {
        $marker = 'QAHTML_' . self::$runId;
        $clientA = $this->client(self::$tokenA);

        $upload = $clientA->postForm('/api/v1/rooms/', [
            'room_id' => self::$roomId,
            'msgtype' => 'm.file',
            'body'    => $marker,
        ], ['file' => self::$tmpHtml], [], ['file' => 'text/html']);
        self::assertSame('ok', $upload->json['status'] ?? null, $upload->rawBody);

        $fileUrl = $this->findFileUrlByMarker($clientA, $marker);
        self::assertNotNull($fileUrl, 'Не удалось найти file_url только что загруженного html-файла через поиск');

        $head = $clientA->head($fileUrl);
        $disposition = $head->header('Content-Disposition') ?? '';
        self::assertStringContainsStringIgnoringCase('attachment', $disposition, 'html-файл должен отдаваться как attachment, а не inline (иначе возможен хранимый XSS)');
    }

    #[Test]
    #[TestDox('Обычная картинка по-прежнему открывается прямо в браузере')]
    public function regularImageIsStillServedInline(): void
    {
        $marker = 'QAIMG2_' . self::$runId;
        $clientA = $this->client(self::$tokenA);

        $upload = $clientA->postForm('/api/v1/rooms/', [
            'room_id' => self::$roomId,
            'msgtype' => 'm.file',
            'body'    => $marker,
        ], ['file' => self::$tmpImage], [], ['file' => 'image/png']);
        self::assertSame('ok', $upload->json['status'] ?? null, $upload->rawBody);

        $fileUrl = $this->findFileUrlByMarker($clientA, $marker);
        self::assertNotNull($fileUrl);

        $head = $clientA->head($fileUrl);
        $disposition = $head->header('Content-Disposition') ?? '';
        self::assertStringContainsStringIgnoringCase('inline', $disposition, 'регресс: обычная картинка должна по-прежнему отдаваться inline');
    }

    private function findFileUrlByMarker(ApiClient $client, string $marker): ?string
    {
        $resp = $client->get('/api/v1/search/?room_id=' . urlencode(self::$roomId) . '&q=' . urlencode($marker));
        foreach ($resp->json ?? [] as $row) {
            $url = $row['json']['content']['file_url'] ?? null;
            if ($url !== null) {
                return $url;
            }
        }
        return null;
    }

    // ---------- Блок 3: членство в поиске / getTyping / members ----------

    #[Test]
    #[TestDox('Не участник не может искать сообщения в чужой комнате')]
    public function nonMemberCannotSearchRoom(): void
    {
        $resp = $this->client(self::$tokenB)->get('/api/v1/search/?room_id=' . urlencode(self::$roomId) . '&q=hello');

        self::assertSame(403, $resp->code);
        self::assertSame('Access denied', $resp->error());
    }

    #[Test]
    #[TestDox('Участник может искать сообщения в своей комнате')]
    public function memberCanSearchOwnRoom(): void
    {
        $resp = $this->client(self::$tokenA)->get('/api/v1/search/?room_id=' . urlencode(self::$roomId) . '&q=hello');

        self::assertSame(200, $resp->code);
        self::assertNotEmpty($resp->json, 'регресс: участник должен находить сообщения своей комнаты');
    }

    #[Test]
    #[TestDox('Не участник не видит индикатор печати в чужой комнате')]
    public function nonMemberCannotSeeTypingIndicator(): void
    {
        $resp = $this->client(self::$tokenB)->get('/api/v1/getTyping/?room_id=' . urlencode(self::$roomId));

        self::assertSame(403, $resp->code);
    }

    #[Test]
    #[TestDox('Участник видит индикатор печати в своей комнате')]
    public function memberCanSeeTypingIndicatorOfOwnRoom(): void
    {
        $resp = $this->client(self::$tokenA)->get('/api/v1/getTyping/?room_id=' . urlencode(self::$roomId));

        self::assertSame(200, $resp->code);
    }

    #[Test]
    #[TestDox('Не участник не видит список участников чужой комнаты')]
    public function nonMemberCannotListMembers(): void
    {
        $resp = $this->client(self::$tokenB)->postJson('/api/v1/rooms/' . self::$roomId . '/members/', []);

        self::assertSame('Access denied', $resp->error());
    }

    #[Test]
    #[TestDox('Участник видит список участников своей комнаты')]
    public function memberCanListMembersOfOwnRoom(): void
    {
        $resp = $this->client(self::$tokenA)->postJson('/api/v1/rooms/' . self::$roomId . '/members/', []);

        self::assertIsArray($resp->json);
        self::assertNotEmpty($resp->json, 'регресс: участник должен видеть список участников своей комнаты');
    }

    // ---------- Блок 5: токены доступа ----------

    #[Test]
    #[TestDox('Payload токена не содержит поле срока действия (exp)')]
    public function tokenPayloadHasNoExpirationClaim(): void
    {
        [, $payloadB64] = explode('.', self::$tokenA);
        $payload = json_decode(base64_decode(strtr($payloadB64, '-_', '+/')), true);

        self::assertArrayNotHasKey('exp', $payload, 'токен не должен нести срок действия по времени');
    }

    #[Test]
    #[TestDox('Запрос с испорченной подписью токена отклоняется')]
    public function tamperedTokenSignatureIsRejected(): void
    {
        $tampered = substr(self::$tokenA, 0, -1) . (substr(self::$tokenA, -1) === 'A' ? 'B' : 'A');

        $resp = $this->client($tampered)->get('/api/v1/joined_rooms/');

        self::assertSame(401, $resp->code);
    }

    #[Test]
    #[TestDox('После логаута токен перестаёт работать')]
    public function logoutInvalidatesToken(): void
    {
        // Отдельный пользователь, созданный прямо в тесте — чтобы не зависеть от порядка
        // выполнения других тестов, использующих токены A/B/C/D.
        $anon = new ApiClient(self::$baseUrl);
        $tokenE = self::registerAndLogin($anon, 'qa_e_' . self::$runId);
        $clientE = $anon->withToken($tokenE);

        $logout = $clientE->postJson('/api/v1/logout/', []);
        self::assertSame(200, $logout->code);

        $afterLogout = $clientE->get('/api/v1/joined_rooms/');
        self::assertSame(401, $afterLogout->code, 'после logout токен должен быть недействителен');
    }
}
