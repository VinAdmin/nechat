<?php

namespace Tests\Unit\Models;

use app\models\Events;
use app\models\EventJson;
use app\models\RoomMemberships;
use app\models\Rooms;
use app\models\Users;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Events::emitDisplayNameChange() рассылает системное событие m.room.member
 * (content.rename = true) во все комнаты, где пользователь состоит со статусом join.
 */
#[TestDox('Рассылка события смены имени по комнатам (Events::emitDisplayNameChange)')]
final class EventsDisplayNameChangeTest extends TestCase
{
    private static string $suffix;
    private static string $user;
    private static string $roomJoinA;
    private static string $roomJoinB;
    private static string $roomInvite;

    public static function setUpBeforeClass(): void
    {
        self::$suffix     = bin2hex(random_bytes(6));
        self::$user       = '@phpunit_dnc_' . self::$suffix . ':test.local';
        self::$roomJoinA  = '!phpunit_dnc_a_' . self::$suffix . ':test.local';
        self::$roomJoinB  = '!phpunit_dnc_b_' . self::$suffix . ':test.local';
        self::$roomInvite = '!phpunit_dnc_i_' . self::$suffix . ':test.local';

        (new Users())->insert(['user_id' => self::$user, 'password' => 'x', 'cdate' => time()]);

        $mRooms = new Rooms();
        $mEvents = new Events();
        $mMemberships = new RoomMemberships();
        foreach ([
            self::$roomJoinA  => 'join',
            self::$roomJoinB  => 'join',
            self::$roomInvite => 'invite',
        ] as $roomId => $membership) {
            $mRooms->insert([
                'room_id'   => $roomId,
                'name'      => 'DNC ' . $membership . ' ' . self::$suffix,
                'topic'     => '',
                'join_rule' => 'public',
                'creator'   => self::$user,
                'cdate'     => time(),
            ]);
            $eventId = $mEvents->addEvent(['type' => 'm.room.member', 'room_id' => $roomId, 'sender' => self::$user]);
            $mMemberships->addUser([
                'event_id'   => $eventId,
                'user_id'    => self::$user,
                'sender'     => self::$user,
                'room_id'    => $roomId,
                'membership' => $membership,
            ]);
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach ([self::$roomJoinA, self::$roomJoinB, self::$roomInvite] as $roomId) {
            (new EventJson())->delete('room_id = :room_id')->execute([':room_id' => $roomId]);
            (new RoomMemberships())->delete('room_id = :room_id')->execute([':room_id' => $roomId]);
            (new Events())->delete('room_id = :room_id')->execute([':room_id' => $roomId]);
            (new Rooms())->delete('room_id = :room_id')->execute([':room_id' => $roomId]);
        }
        (new Users())->delete('user_id = :user_id')->execute([':user_id' => self::$user]);
    }

    /** @return array<int,array> распарсенные JSON событий m.room.member с rename=true в комнате */
    private function renameEvents(string $roomId): array
    {
        $mJson = new EventJson();
        $mJson->select("t1.json")->from()
              ->joinInner(['e' => 'events'], "e.event_id = t1.event_id")
              ->where("t1.room_id = :room_id AND e.type = 'm.room.member' ORDER BY e.id ASC");
        $rows = $mJson->fetchAll(['room_id' => $roomId]);

        $out = [];
        foreach ($rows as $row) {
            $j = json_decode($row['json'], true);
            if (($j['content']['rename'] ?? false) === true) {
                $out[] = $j;
            }
        }
        return $out;
    }

    #[TestDox('Смена имени: событие rename появляется в каждой join-комнате, но не в invite')]
    public function testEmitsRenameEventToEveryJoinedRoom(): void
    {
        (new Events())->emitDisplayNameChange(self::$user, 'Старое', 'Новое', false);

        $a = $this->renameEvents(self::$roomJoinA);
        $b = $this->renameEvents(self::$roomJoinB);

        self::assertCount(1, $a);
        self::assertCount(1, $b);
        self::assertCount(0, $this->renameEvents(self::$roomInvite));

        self::assertSame('m.room.member', $a[0]['type']);
        self::assertSame(self::$user, $a[0]['sender']);
        self::assertSame('join', $a[0]['content']['membership']);
        self::assertSame('Старое', $a[0]['content']['prev_displayname']);
        self::assertSame('Новое', $a[0]['content']['displayname']);
        self::assertFalse($a[0]['content']['cleared']);
    }

    #[TestDox('Очистка имени: событие несёт cleared = true и новое отображаемое имя = user_id')]
    public function testEmitsClearedFlagWhenNameRemoved(): void
    {
        (new Events())->emitDisplayNameChange(self::$user, 'Старое', self::$user, true);

        $events = $this->renameEvents(self::$roomJoinA);
        $last = end($events);

        self::assertTrue($last['content']['cleared']);
        self::assertSame(self::$user, $last['content']['displayname']);
    }
}
