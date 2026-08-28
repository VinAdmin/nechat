<?php

namespace Tests\Unit\Models;

use app\models\Events;
use app\models\RoomMemberships;
use app\models\Rooms;
use app\models\Users;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * RoomMemberships::getJoinedRoomIds() — список room_id, где пользователь состоит
 * со статусом join (нужен для рассылки события смены имени по комнатам).
 */
#[TestDox('Список комнат пользователя со статусом join')]
final class RoomMembershipsJoinedRoomIdsTest extends TestCase
{
    private static string $suffix;
    private static string $user;
    private static string $roomJoinA;
    private static string $roomJoinB;
    private static string $roomInvite;
    private static string $roomBan;

    public static function setUpBeforeClass(): void
    {
        self::$suffix     = bin2hex(random_bytes(6));
        self::$user       = '@phpunit_jri_' . self::$suffix . ':test.local';
        self::$roomJoinA  = '!phpunit_jri_a_' . self::$suffix . ':test.local';
        self::$roomJoinB  = '!phpunit_jri_b_' . self::$suffix . ':test.local';
        self::$roomInvite = '!phpunit_jri_i_' . self::$suffix . ':test.local';
        self::$roomBan    = '!phpunit_jri_x_' . self::$suffix . ':test.local';

        (new Users())->insert(['user_id' => self::$user, 'password' => 'x', 'cdate' => time()]);

        $mRooms = new Rooms();
        $mEvents = new Events();
        $mMemberships = new RoomMemberships();

        foreach ([
            self::$roomJoinA  => 'join',
            self::$roomJoinB  => 'join',
            self::$roomInvite => 'invite',
            self::$roomBan    => 'ban',
        ] as $roomId => $membership) {
            $mRooms->insert([
                'room_id'   => $roomId,
                'name'      => 'JRI ' . $membership . ' ' . self::$suffix,
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
        foreach ([self::$roomJoinA, self::$roomJoinB, self::$roomInvite, self::$roomBan] as $roomId) {
            (new RoomMemberships())->delete('room_id = :room_id')->execute([':room_id' => $roomId]);
            (new Events())->delete('room_id = :room_id')->execute([':room_id' => $roomId]);
            (new Rooms())->delete('room_id = :room_id')->execute([':room_id' => $roomId]);
        }
        (new Users())->delete('user_id = :user_id')->execute([':user_id' => self::$user]);
    }

    #[TestDox('Возвращает только комнаты со статусом join (не invite, не ban)')]
    public function testReturnsOnlyJoinedRoomIds(): void
    {
        $ids = (new RoomMemberships())->getJoinedRoomIds(self::$user);

        sort($ids);
        $expected = [self::$roomJoinA, self::$roomJoinB];
        sort($expected);

        self::assertSame($expected, $ids);
    }

    #[TestDox('Для пользователя без комнат возвращает пустой массив')]
    public function testReturnsEmptyForUserWithoutRooms(): void
    {
        $ids = (new RoomMemberships())->getJoinedRoomIds('@phpunit_jri_nobody_' . self::$suffix . ':test.local');

        self::assertSame([], $ids);
    }
}
