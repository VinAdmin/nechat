<?php

namespace Tests\Unit\Models;

use app\models\Events;
use app\models\PowerLevels;
use app\models\RoomMemberships;
use app\models\Rooms;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Rooms читает состояние прав из последнего события m.room.power_levels')]
final class PowerLevelsRoomsTest extends TestCase
{
    private static string $suffix;
    private static string $roomNoEvent;
    private static string $roomWithEvent;
    private static string $creator;
    private static string $mod;

    public static function setUpBeforeClass(): void
    {
        self::$suffix = bin2hex(random_bytes(6));
        self::$roomNoEvent = '!pl_none_' . self::$suffix . ':test.local';
        self::$roomWithEvent = '!pl_evt_' . self::$suffix . ':test.local';
        self::$creator = '@pl_creator_' . self::$suffix . ':test.local';
        self::$mod = '@pl_mod_' . self::$suffix . ':test.local';

        $mRooms = new Rooms();
        foreach ([self::$roomNoEvent, self::$roomWithEvent] as $rid) {
            $mRooms->insert([
                'room_id' => $rid, 'name' => 'PL ' . self::$suffix, 'topic' => '',
                'join_rule' => 'public', 'creator' => self::$creator, 'cdate' => time(),
            ]);
        }

        // roomWithEvent: событие прав, где mod = 50, ban повышен до 60
        $levels = array_merge(PowerLevels::DEFAULTS, [
            'users' => [self::$creator => 100, self::$mod => 50],
            'ban' => 60,
        ]);
        (new Events())->setPowerLevels(self::$roomWithEvent, self::$creator, $levels);
    }

    #[TestDox('Комната без события прав отдаёт дефолты, создатель = 100')]
    public function testDefaultsWhenNoEvent(): void
    {
        $mRooms = new Rooms();
        $levels = $mRooms->getPowerLevels(self::$roomNoEvent);
        self::assertSame(50, $levels['ban']);
        self::assertSame(100, PowerLevels::levelForUser($levels, self::$creator, self::$creator));
        self::assertSame(0, PowerLevels::levelForUser($levels, self::$mod, self::$creator));
    }

    #[TestDox('Комната с событием прав отдаёт значения из события')]
    public function testReadsFromLatestEvent(): void
    {
        $mRooms = new Rooms();
        $levels = $mRooms->getPowerLevels(self::$roomWithEvent);
        self::assertSame(60, $levels['ban']);
        self::assertSame(50, PowerLevels::levelForUser($levels, self::$mod, self::$creator));
    }

    #[TestDox('Свежее событие прав перезаписывает предыдущее')]
    public function testLatestEventWins(): void
    {
        $mEvents = new Events();
        $mRooms = new Rooms();
        $current = $mRooms->getPowerLevels(self::$roomWithEvent);
        $mEvents->setPowerLevels(self::$roomWithEvent, self::$creator,
            PowerLevels::applyChange($current, ['thresholds' => ['ban' => 70]]));
        self::assertSame(70, $mRooms->getPowerLevels(self::$roomWithEvent)['ban']);
    }

    #[TestDox('canDo: создатель может ban всегда; сторонний пользователь (уровень 0) — нет')]
    public function testCanDo(): void
    {
        $mRooms = new Rooms();
        self::assertTrue($mRooms->canDo(self::$roomNoEvent, self::$creator, 'ban'));
        self::assertFalse($mRooms->canDo(self::$roomNoEvent, self::$mod, 'ban'), 'mod вне users → уровень 0');
    }

    #[TestDox('Стартовая последовательность insert + setPowerLevels даёт creator=100 и дефолтные пороги')]
    public function testCreateRoomEmitsInitialEvent(): void
    {
        $rid = '!pl_init_' . bin2hex(random_bytes(6)) . ':test.local';
        $owner = '@pl_init_owner_' . bin2hex(random_bytes(4)) . ':test.local';
        $mRooms = new Rooms();
        $mRooms->insert([
            'room_id' => $rid, 'name' => 'init', 'topic' => '',
            'join_rule' => 'public', 'creator' => $owner, 'cdate' => time(),
        ]);
        (new Events())->setPowerLevels($rid, $owner, array_merge(
            PowerLevels::DEFAULTS, ['users' => [$owner => PowerLevels::OWNER_LEVEL]]
        ));

        $levels = $mRooms->getPowerLevels($rid);
        self::assertSame(100, $levels['users'][$owner]);
        self::assertSame(50, $levels['state_default']);
    }
}
