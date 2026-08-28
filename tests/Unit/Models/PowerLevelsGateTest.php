<?php

namespace Tests\Unit\Models;

use app\models\Events;
use app\models\PowerLevels;
use app\models\RoomMemberships;
use app\models\Rooms;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('events_default гейтит отправку сообщений (announcement-режим)')]
final class PowerLevelsGateTest extends TestCase
{
    private static string $suffix;
    private static string $announceRoom;
    private static string $plainRoom;
    private static string $owner;
    private static string $lowMember;
    private static string $modMember;

    public static function setUpBeforeClass(): void
    {
        self::$suffix = bin2hex(random_bytes(6));
        self::$announceRoom = '!pl_ann_' . self::$suffix . ':test.local';
        self::$plainRoom = '!pl_plain_' . self::$suffix . ':test.local';
        self::$owner = '@pl_g_owner_' . self::$suffix . ':test.local';
        self::$lowMember = '@pl_g_low_' . self::$suffix . ':test.local';
        self::$modMember = '@pl_g_mod_' . self::$suffix . ':test.local';

        $mRooms = new Rooms();
        $mEvents = new Events();
        $mMemberships = new RoomMemberships();

        foreach ([self::$announceRoom, self::$plainRoom] as $rid) {
            $mRooms->insert([
                'room_id' => $rid, 'name' => 'g ' . self::$suffix, 'topic' => '',
                'join_rule' => 'public', 'creator' => self::$owner, 'cdate' => time(),
            ]);
            foreach ([self::$owner, self::$lowMember, self::$modMember] as $uid) {
                $eid = $mEvents->addEvent(['type' => 'm.room.member', 'room_id' => $rid, 'sender' => self::$owner]);
                $mMemberships->addUser([
                    'event_id' => $eid, 'user_id' => $uid, 'sender' => self::$owner,
                    'room_id' => $rid, 'membership' => 'join',
                ]);
            }
        }

        // announceRoom: events_default = 50, modMember = 50
        $levels = array_merge(PowerLevels::DEFAULTS, [
            'users' => [self::$owner => 100, self::$modMember => 50],
            'events_default' => 50,
        ]);
        $mEvents->setPowerLevels(self::$announceRoom, self::$owner, $levels);
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    private function send(string $room, string $sender): array
    {
        $_POST = ['room_id' => $room, 'msgtype' => 'm.text', 'body' => 'hi'];
        return json_decode((new Events())->create($sender), true);
    }

    #[TestDox('В обычной комнате без события прав любой участник пишет свободно')]
    public function testPlainRoomAllowsAllMembers(): void
    {
        self::assertSame('ok', $this->send(self::$plainRoom, self::$lowMember)['status'] ?? null);
    }

    #[TestDox('В announcement-комнате участник с уровнем 0 не может писать')]
    public function testAnnounceBlocksLowMember(): void
    {
        self::assertSame('Sending a message is prohibited', $this->send(self::$announceRoom, self::$lowMember)['error'] ?? null);
    }

    #[TestDox('В announcement-комнате модератор (50) и владелец (100) пишут')]
    public function testAnnounceAllowsModAndOwner(): void
    {
        self::assertSame('ok', $this->send(self::$announceRoom, self::$modMember)['status'] ?? null);
        self::assertSame('ok', $this->send(self::$announceRoom, self::$owner)['status'] ?? null);
    }
}
