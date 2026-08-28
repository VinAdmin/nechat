<?php

namespace Tests\Unit\Models;

use app\models\Events;
use app\models\PowerLevels;
use app\models\RoomMemberships;
use app\models\Rooms;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Правила назначения уровней и порог state_default')]
final class PowerLevelsAssignTest extends TestCase
{
    private static string $suffix;
    private static string $roomId;
    private static string $owner;
    private static string $mod;
    private static string $plain;

    public static function setUpBeforeClass(): void
    {
        self::$suffix = bin2hex(random_bytes(6));
        self::$roomId = '!pl_asg_' . self::$suffix . ':test.local';
        self::$owner = '@pl_a_owner_' . self::$suffix . ':test.local';
        self::$mod = '@pl_a_mod_' . self::$suffix . ':test.local';
        self::$plain = '@pl_a_plain_' . self::$suffix . ':test.local';

        $mRooms = new Rooms();
        $mRooms->insert([
            'room_id' => self::$roomId, 'name' => 'asg', 'topic' => '',
            'join_rule' => 'public', 'creator' => self::$owner, 'cdate' => time(),
        ]);
        $mEvents = new Events();
        $mMemberships = new RoomMemberships();
        foreach ([self::$owner, self::$mod, self::$plain] as $uid) {
            $eid = $mEvents->addEvent(['type' => 'm.room.member', 'room_id' => self::$roomId, 'sender' => self::$owner]);
            $mMemberships->addUser([
                'event_id' => $eid, 'user_id' => $uid, 'sender' => self::$owner,
                'room_id' => self::$roomId, 'membership' => 'join',
            ]);
        }
        $mEvents->setPowerLevels(self::$roomId, self::$owner, array_merge(
            PowerLevels::DEFAULTS,
            ['users' => [self::$owner => 100, self::$mod => 50]]
        ));
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    #[TestDox('canDo(state_default): mod (50) может, plain (0) не может')]
    public function testStateDefaultGate(): void
    {
        $mRooms = new Rooms();
        self::assertTrue($mRooms->canDo(self::$roomId, self::$mod, 'state_default'));
        self::assertFalse($mRooms->canDo(self::$roomId, self::$plain, 'state_default'));
        self::assertTrue($mRooms->canDo(self::$roomId, self::$owner, 'state_default'));
    }

    #[TestDox('userPowerLevel: иерархия owner(100) > mod(50) > plain(0)')]
    public function testHierarchy(): void
    {
        $mRooms = new Rooms();
        self::assertSame(100, $mRooms->userPowerLevel(self::$roomId, self::$owner));
        self::assertSame(50, $mRooms->userPowerLevel(self::$roomId, self::$mod));
        self::assertSame(0, $mRooms->userPowerLevel(self::$roomId, self::$plain));
    }

    #[TestDox('Назначение уровня выше своего запрещено')]
    public function testCannotAssignAboveOwn(): void
    {
        $mRooms = new Rooms();
        $levels = $mRooms->getPowerLevels(self::$roomId);
        $actorLevel = PowerLevels::levelForUser($levels, self::$mod, self::$owner);
        $check = PowerLevels::canAssignLevel($actorLevel, self::$mod, self::$plain, 0, 90);
        self::assertFalse($check['ok']);
        self::assertSame('Cannot assign a level above your own', $check['error']);
    }

    #[TestDox('Успешное назначение эмитит ровно одно новое событие прав')]
    public function testSuccessfulAssignEmitsOneEvent(): void
    {
        $mRooms = new Rooms();
        $mEvents = new Events();

        $before = $this->countPowerEvents();
        $levels = $mRooms->getPowerLevels(self::$roomId);
        $next = PowerLevels::applyChange($levels, ['user_id' => self::$plain, 'level' => 50]);
        $mEvents->setPowerLevels(self::$roomId, self::$owner, $next);

        self::assertSame($before + 1, $this->countPowerEvents());
        self::assertSame(50, $mRooms->getPowerLevels(self::$roomId)['users'][self::$plain]);
    }

    private function countPowerEvents(): int
    {
        $mEvents = new Events();
        $mEvents->select('COUNT(*) AS c')->from()
            ->where("room_id = :room_id AND type = 'm.room.power_levels'");
        $row = $mEvents->fetch(['room_id' => self::$roomId]);
        return (int) ($row['c'] ?? 0);
    }
}
