<?php

namespace Tests\Unit\Models;

use app\models\Events;
use app\models\RoomMemberships;
use app\models\Rooms;
use app\models\Users;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * RoomMemberships::getRoomMembers() отдаёт вместе с членством текущее имя
 * пользователя (users.name), чтобы список участников показывал имя, а не логин.
 */
#[TestDox('Имя пользователя в списке участников комнаты')]
final class RoomMembersNameTest extends TestCase
{
    private static string $suffix;
    private static string $roomId;
    private static string $named;
    private static string $noName;

    public static function setUpBeforeClass(): void
    {
        self::$suffix = bin2hex(random_bytes(6));
        self::$roomId = '!phpunit_rm_' . self::$suffix . ':test.local';
        self::$named  = '@phpunit_rm_named_' . self::$suffix . ':test.local';
        self::$noName = '@phpunit_rm_noname_' . self::$suffix . ':test.local';

        $mUsers = new Users();
        $mUsers->insert(['user_id' => self::$named,  'password' => 'x', 'cdate' => time()]);
        $mUsers->insert(['user_id' => self::$noName, 'password' => 'x', 'cdate' => time()]);
        $mUsers->Update(['name' => 'Алиса', 'user_id' => self::$named], 'user_id = :user_id');

        (new Rooms())->insert([
            'room_id'   => self::$roomId,
            'name'      => 'RM room ' . self::$suffix,
            'topic'     => '',
            'join_rule' => 'public',
            'creator'   => self::$named,
            'cdate'     => time(),
        ]);

        $mEvents = new Events();
        $mMemberships = new RoomMemberships();
        foreach ([self::$named, self::$noName] as $userId) {
            $eventId = $mEvents->addEvent(['type' => 'm.room.member', 'room_id' => self::$roomId, 'sender' => $userId]);
            $mMemberships->addUser([
                'event_id'   => $eventId,
                'user_id'    => $userId,
                'sender'     => $userId,
                'room_id'    => self::$roomId,
                'membership' => 'join',
            ]);
        }
    }

    public static function tearDownAfterClass(): void
    {
        (new Events())->delete('room_id = :room_id')->execute([':room_id' => self::$roomId]);
        (new RoomMemberships())->delete('room_id = :room_id')->execute([':room_id' => self::$roomId]);
        (new Rooms())->delete('room_id = :room_id')->execute([':room_id' => self::$roomId]);
        (new Users())->delete('user_id = :user_id')->execute([':user_id' => self::$named]);
        (new Users())->delete('user_id = :user_id')->execute([':user_id' => self::$noName]);
    }

    #[TestDox('getRoomMembers() возвращает users.name для каждого участника')]
    public function testGetRoomMembersIncludesName(): void
    {
        $members = (new RoomMemberships())->getRoomMembers(self::$roomId);

        $byUser = [];
        foreach ($members as $m) {
            $byUser[$m['user_id']] = $m;
        }

        self::assertArrayHasKey('name', $byUser[self::$named]);
        self::assertSame('Алиса', $byUser[self::$named]['name']);
        self::assertArrayHasKey('name', $byUser[self::$noName]);
        self::assertNull($byUser[self::$noName]['name']);
    }

    #[TestDox('getRoomMembers() сохраняет ключи членства (user_id, membership, event_id)')]
    public function testGetRoomMembersKeepsMembershipFields(): void
    {
        $members = (new RoomMemberships())->getRoomMembers(self::$roomId);

        self::assertNotEmpty($members);
        foreach ($members as $m) {
            self::assertArrayHasKey('user_id', $m);
            self::assertArrayHasKey('membership', $m);
            self::assertArrayHasKey('event_id', $m);
        }
    }
}
