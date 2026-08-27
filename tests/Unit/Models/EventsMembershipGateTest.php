<?php

namespace Tests\Unit\Models;

use app\models\Events;
use app\models\Rooms;
use app\models\RoomMemberships;
use app\models\Users;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тест на позитивную проверку членства в Events::create() (фикс из
 * security-fixes-report.md, п.1): отправлять сообщение может только участник со статусом
 * join, а не "все, кроме явно забаненных/приглашённых".
 *
 * Events::create() читает тело запроса через php://input с фолбэком на $_POST — в CLI
 * php://input пуст, поэтому используем $_POST напрямую.
 */
#[TestDox('Право на отправку сообщения проверяется по членству в комнате (Events::create)')]
final class EventsMembershipGateTest extends TestCase
{
    private static string $roomId;
    private static string $userJoin;
    private static string $userBanned;
    private static string $userInvited;
    private static string $userStranger; // вообще без записи членства в этой комнате

    public static function setUpBeforeClass(): void
    {
        $suffix = bin2hex(random_bytes(6));
        self::$roomId = '!phpunit_' . $suffix . ':test.local';
        self::$userJoin = '@phpunit_join_' . $suffix . ':test.local';
        self::$userBanned = '@phpunit_ban_' . $suffix . ':test.local';
        self::$userInvited = '@phpunit_invite_' . $suffix . ':test.local';
        self::$userStranger = '@phpunit_stranger_' . $suffix . ':test.local';

        $mRooms = new Rooms();
        $mRooms->insert([
            'room_id'   => self::$roomId,
            'name'      => 'PHPUnit room ' . $suffix,
            'topic'     => '',
            'join_rule' => 'public',
            'creator'   => self::$userJoin,
            'cdate'     => time(),
        ]);

        $mEvents = new Events();
        $mMemberships = new RoomMemberships();

        foreach ([
            self::$userJoin    => 'join',
            self::$userBanned  => 'ban',
            self::$userInvited => 'invite',
        ] as $userId => $membership) {
            $eventId = $mEvents->addEvent([
                'type'    => 'm.room.member',
                'room_id' => self::$roomId,
                'sender'  => self::$userJoin,
            ]);
            $mMemberships->addUser([
                'event_id'   => $eventId,
                'user_id'    => $userId,
                'sender'     => self::$userJoin,
                'room_id'    => self::$roomId,
                'membership' => $membership,
            ]);
        }
        // self::$userStranger намеренно не получает вообще никакой записи членства —
        // именно этот случай раньше (до фикса) ошибочно пропускался.
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    private function sendText(string $sender, string $body = 'hello from phpunit'): array
    {
        $_POST = [
            'room_id' => self::$roomId,
            'msgtype' => 'm.text',
            'body'    => $body,
        ];

        $mEvents = new Events();
        return json_decode($mEvents->create($sender), true);
    }

    #[TestDox('Участник комнаты (статус join) может отправить сообщение')]
    public function testMemberWithJoinCanSendMessage(): void
    {
        $result = $this->sendText(self::$userJoin);

        self::assertSame('ok', $result['status'] ?? null, json_encode($result));
        self::assertArrayHasKey('event_id', $result);
    }

    #[TestDox('Забаненный пользователь не может отправить сообщение')]
    public function testBannedUserCannotSendMessage(): void
    {
        $result = $this->sendText(self::$userBanned);

        self::assertSame('Sending a message is prohibited', $result['error'] ?? null);
    }

    #[TestDox('Приглашённый, но не вступивший пользователь не может отправить сообщение')]
    public function testInvitedButNotJoinedUserCannotSendMessage(): void
    {
        $result = $this->sendText(self::$userInvited);

        self::assertSame('Sending a message is prohibited', $result['error'] ?? null);
    }

    #[TestDox('Посторонний без записи о членстве не может отправить сообщение (сама уязвимость)')]
    public function testStrangerWithNoMembershipRowCannotSendMessage(): void
    {
        // Регресс-тест на исходную уязвимость: раньше отсутствие записи членства
        // ошибочно трактовалось как "не забанен и не приглашён — можно постить".
        $result = $this->sendText(self::$userStranger);

        self::assertSame('Sending a message is prohibited', $result['error'] ?? null);
    }
}
