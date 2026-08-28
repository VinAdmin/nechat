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
 * Users::updateProfile() при реальной смене поля name рассылает системное
 * событие смены имени (Events::emitDisplayNameChange) по join-комнатам
 * пользователя. При no-op или когда name не передан — событий не создаёт.
 */
#[TestDox('updateProfile рассылает событие смены имени')]
final class UsersRenameBroadcastTest extends TestCase
{
    private string $suffix;
    private string $user;
    private string $room;

    protected function setUp(): void
    {
        $this->suffix = bin2hex(random_bytes(6));
        $this->user   = '@phpunit_rb_' . $this->suffix . ':test.local';
        $this->room   = '!phpunit_rb_' . $this->suffix . ':test.local';

        (new Users())->insert(['user_id' => $this->user, 'password' => 'x', 'cdate' => time()]);
        (new Rooms())->insert([
            'room_id'   => $this->room,
            'name'      => 'RB ' . $this->suffix,
            'topic'     => '',
            'join_rule' => 'public',
            'creator'   => $this->user,
            'cdate'     => time(),
        ]);
        $eventId = (new Events())->addEvent(['type' => 'm.room.member', 'room_id' => $this->room, 'sender' => $this->user]);
        (new RoomMemberships())->addUser([
            'event_id'   => $eventId,
            'user_id'    => $this->user,
            'sender'     => $this->user,
            'room_id'    => $this->room,
            'membership' => 'join',
        ]);
    }

    protected function tearDown(): void
    {
        (new EventJson())->delete('room_id = :room_id')->execute([':room_id' => $this->room]);
        (new RoomMemberships())->delete('room_id = :room_id')->execute([':room_id' => $this->room]);
        (new Events())->delete('room_id = :room_id')->execute([':room_id' => $this->room]);
        (new Rooms())->delete('room_id = :room_id')->execute([':room_id' => $this->room]);
        (new Users())->delete('user_id = :user_id')->execute([':user_id' => $this->user]);
    }

    /** @return array<int,array> content'ы событий rename в комнате */
    private function renameEventContents(): array
    {
        $mJson = new EventJson();
        $mJson->select("t1.json")->from()
              ->joinInner(['e' => 'events'], "e.event_id = t1.event_id")
              ->where("t1.room_id = :room_id AND e.type = 'm.room.member' ORDER BY e.id ASC");
        $out = [];
        foreach ($mJson->fetchAll(['room_id' => $this->room]) as $row) {
            $j = json_decode($row['json'], true);
            if (($j['content']['rename'] ?? false) === true) {
                $out[] = $j['content'];
            }
        }
        return $out;
    }

    #[TestDox('Первая установка имени: событие с prev_displayname = user_id')]
    public function testFirstNameSetEmitsEvent(): void
    {
        (new Users())->updateProfile($this->user, ['name' => 'Алиса']);

        $events = $this->renameEventContents();
        self::assertCount(1, $events);
        self::assertSame($this->user, $events[0]['prev_displayname']);
        self::assertSame('Алиса', $events[0]['displayname']);
        self::assertFalse($events[0]['cleared']);
    }

    #[TestDox('Смена имени на другое: prev = старое имя, displayname = новое')]
    public function testRenameEmitsEventWithBothNames(): void
    {
        (new Users())->updateProfile($this->user, ['name' => 'Алиса']);
        (new Users())->updateProfile($this->user, ['name' => 'Боб']);

        $events = $this->renameEventContents();
        self::assertCount(2, $events);
        self::assertSame('Алиса', $events[1]['prev_displayname']);
        self::assertSame('Боб', $events[1]['displayname']);
    }

    #[TestDox('Очистка имени: событие с cleared = true')]
    public function testClearingNameEmitsClearedEvent(): void
    {
        (new Users())->updateProfile($this->user, ['name' => 'Алиса']);
        (new Users())->updateProfile($this->user, ['name' => '']);

        $events = $this->renameEventContents();
        self::assertCount(2, $events);
        self::assertTrue($events[1]['cleared']);
        self::assertSame('Алиса', $events[1]['prev_displayname']);
        self::assertSame($this->user, $events[1]['displayname']);
    }

    #[TestDox('Повторная установка того же имени не создаёт событие')]
    public function testNoOpNameDoesNotEmit(): void
    {
        (new Users())->updateProfile($this->user, ['name' => 'Алиса']);
        (new Users())->updateProfile($this->user, ['name' => 'Алиса']);

        self::assertCount(1, $this->renameEventContents());
    }

    #[TestDox('Обновление профиля без поля name не создаёт событие')]
    public function testUpdateWithoutNameKeyDoesNotEmit(): void
    {
        (new Users())->updateProfile($this->user, ['avatar_url' => '/f/x.png']);

        self::assertCount(0, $this->renameEventContents());
    }
}
