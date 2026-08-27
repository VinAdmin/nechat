<?php

namespace Tests\Unit\Models;

use app\models\Events;
use app\models\EventJson;
use app\models\Rooms;
use app\models\RoomMemberships;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест на проверку членства в комнате при синхронизации (Events::sync()).
 *
 * Безопасностное свойство: GET /api/v1/sync/ должен отдавать события ТОЛЬКО из комнат,
 * где пользователь состоит (membership = 'join') или приглашён ('invite'). Ни содержимое
 * сообщений чужих комнат, ни сам факт их существования (room_id) не должны попадать в
 * ответ sync пользователю без членства или со статусом 'ban'. Приглашённый (invite)
 * видит комнату в списке, но не видит тел сообщений (invite_state.events всегда пуст).
 *
 * Events::sync() читает `since` через filter_input(INPUT_GET, ...) — в CLI это null,
 * поэтому тест проверяет полную (без since) синхронизацию.
 *
 * Требует реально настроенной БД (config/db.php) — модели проекта подключаются к БД
 * напрямую, слоя мокирования нет. Тестовые комнаты/события/членства не удаляются
 * автоматически (в API нет метода удаления), но используют уникальный суффикс и не
 * пересекаются с данными других тестов.
 */
#[TestDox('Синхронизация отдаёт события только из комнат пользователя (Events::sync)')]
final class EventsSyncMembershipTest extends TestCase
{
    private static string $suffix;
    private static string $userMain;
    private static string $userOwner;

    private static string $roomJoined;
    private static string $roomInvited;
    private static string $roomStranger; // userMain вообще без записи членства
    private static string $roomBanned;   // userMain со статусом ban

    private static string $bodyJoined = 'MSG_JOINED_';
    private static string $bodyInvited = 'MSG_INVITED_';
    private static string $bodyStranger = 'MSG_STRANGER_';
    private static string $bodyBanned = 'MSG_BANNED_';

    public static function setUpBeforeClass(): void
    {
        self::$suffix = bin2hex(random_bytes(6));
        self::$userMain = '@phpunit_sync_main_' . self::$suffix . ':test.local';
        self::$userOwner = '@phpunit_sync_owner_' . self::$suffix . ':test.local';

        self::$roomJoined   = '!phpunit_sync_join_' . self::$suffix . ':test.local';
        self::$roomInvited  = '!phpunit_sync_invite_' . self::$suffix . ':test.local';
        self::$roomStranger = '!phpunit_sync_stranger_' . self::$suffix . ':test.local';
        self::$roomBanned   = '!phpunit_sync_banned_' . self::$suffix . ':test.local';

        self::$bodyJoined   .= self::$suffix;
        self::$bodyInvited  .= self::$suffix;
        self::$bodyStranger .= self::$suffix;
        self::$bodyBanned   .= self::$suffix;

        $mRooms = new Rooms();
        foreach ([
            self::$roomJoined   => 'Sync join room ',
            self::$roomInvited  => 'Sync invite room ',
            self::$roomStranger => 'Sync stranger room ',
            self::$roomBanned   => 'Sync banned room ',
        ] as $roomId => $name) {
            $mRooms->insert([
                'room_id'   => $roomId,
                'name'      => $name . self::$suffix,
                'topic'     => '',
                'join_rule' => 'public',
                'creator'   => self::$userOwner,
                'cdate'     => time(),
            ]);
        }

        // Членство userMain в каждой комнате (кроме roomStranger — там записи нет вообще).
        self::addMembership(self::$roomJoined, self::$userMain, 'join');
        self::addMembership(self::$roomInvited, self::$userMain, 'invite');
        self::addMembership(self::$roomBanned, self::$userMain, 'ban');

        // Владелец состоит во всех комнатах — чтобы в каждой были осмысленные события.
        foreach ([self::$roomJoined, self::$roomInvited, self::$roomStranger, self::$roomBanned] as $roomId) {
            self::addMembership($roomId, self::$userOwner, 'join');
        }

        // По одному текстовому событию в каждой комнате (напрямую через модели, минуя
        // Events::create() — он бы отклонил отправку в комнаты без join у отправителя).
        self::addTextEvent(self::$roomJoined, self::$bodyJoined);
        self::addTextEvent(self::$roomInvited, self::$bodyInvited);
        self::addTextEvent(self::$roomStranger, self::$bodyStranger);
        self::addTextEvent(self::$roomBanned, self::$bodyBanned);
    }

    private static function addMembership(string $roomId, string $userId, string $membership): void
    {
        $mEvents = new Events();
        $eventId = $mEvents->addEvent([
            'type'    => 'm.room.member',
            'room_id' => $roomId,
            'sender'  => self::$userOwner,
        ]);

        (new RoomMemberships())->addUser([
            'event_id'   => $eventId,
            'user_id'    => $userId,
            'sender'     => self::$userOwner,
            'room_id'    => $roomId,
            'membership' => $membership,
        ]);
    }

    private static function addTextEvent(string $roomId, string $body): void
    {
        $mEvents = new Events();
        $eventId = $mEvents->addEvent([
            'type'    => 'm.room.message',
            'room_id' => $roomId,
            'sender'  => self::$userOwner,
        ]);

        (new EventJson())->add([
            'event_id' => $eventId,
            'room_id'  => $roomId,
            'json'     => json_encode([
                'event_id' => $eventId,
                'type'     => 'm.room.message',
                'room_id'  => $roomId,
                'sender'   => self::$userOwner,
                'content'  => ['body' => $body, 'msgtype' => 'm.text'],
            ]),
        ]);
    }

    private function sync(): array
    {
        return json_decode((new Events())->sync(self::$userMain), true);
    }

    #[TestDox('Участник (join) получает события своей комнаты в блоке rooms.join')]
    public function testSyncReturnsEventsFromJoinedRoom(): void
    {
        $result = $this->sync();

        self::assertArrayHasKey('join', $result['rooms'] ?? [], json_encode($result));
        self::assertArrayHasKey(self::$roomJoined, $result['rooms']['join']);

        $found = false;
        foreach ($result['rooms']['join'][self::$roomJoined]['events'] as $event) {
            if (($event['json']['content']['body'] ?? null) === self::$bodyJoined) {
                $found = true;
            }
        }
        self::assertTrue($found, 'Сообщение из комнаты со статусом join должно попадать в sync');
    }

    #[TestDox('Комната без записи членства не раскрывается в sync (ни room_id, ни тела сообщений)')]
    public function testSyncDoesNotLeakRoomWithoutMembership(): void
    {
        $raw = (new Events())->sync(self::$userMain);

        self::assertStringNotContainsString(self::$roomStranger, $raw, 'room_id чужой комнаты не должен попадать в ответ sync');
        self::assertStringNotContainsString(self::$bodyStranger, $raw, 'тело сообщения чужой комнаты не должно попадать в ответ sync');
    }

    #[TestDox('Комната, где пользователь забанен (ban), не раскрывается в sync')]
    public function testSyncDoesNotLeakBannedRoom(): void
    {
        $raw = (new Events())->sync(self::$userMain);
        $result = json_decode($raw, true);

        self::assertArrayNotHasKey(self::$roomBanned, $result['rooms']['join'] ?? []);
        self::assertArrayNotHasKey(self::$roomBanned, $result['rooms']['invite'] ?? []);
        self::assertStringNotContainsString(self::$bodyBanned, $raw, 'тело сообщения из комнаты, где пользователь забанен, не должно попадать в sync');
    }

    #[TestDox('Приглашённый видит комнату в rooms.invite, но без тел сообщений')]
    public function testSyncExposesInviteRoomWithoutMessageBodies(): void
    {
        $raw = (new Events())->sync(self::$userMain);
        $result = json_decode($raw, true);

        self::assertArrayHasKey('invite', $result['rooms'] ?? [], $raw);
        self::assertArrayHasKey(self::$roomInvited, $result['rooms']['invite']);
        self::assertSame(
            [],
            $result['rooms']['invite'][self::$roomInvited]['invite_state']['events'],
            'invite_state.events должен быть пуст — приглашённый не видит историю сообщений'
        );
        self::assertStringNotContainsString(self::$bodyInvited, $raw, 'тело сообщения не должно раскрываться приглашённому до вступления');
    }
}
