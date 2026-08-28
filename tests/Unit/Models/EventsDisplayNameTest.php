<?php

namespace Tests\Unit\Models;

use app\models\Events;
use app\models\EventJson;
use app\models\Rooms;
use wco\kernel\WCO;
use app\models\RoomMemberships;
use app\models\Users;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Отображаемое имя отправителя денормализуется в JSON события при отправке
 * (по аналогии с avatar_url): в m.room.message — content.displayname, в цитате —
 * reply_to.displayname, в событиях членства m.room.member — content.displayname.
 *
 * Events::create() читает php://input с фолбэком на $_POST — в CLI используем $_POST.
 */
#[TestDox('Имя отправителя в событиях (displayname)')]
final class EventsDisplayNameTest extends TestCase
{
    private static string $suffix;
    private static string $roomId;
    private static string $named;   // пользователь с заполненным name
    private static string $noName;  // пользователь без name

    public static function setUpBeforeClass(): void
    {
        self::$suffix = bin2hex(random_bytes(6));
        self::$roomId = '!phpunit_dn_' . self::$suffix . ':test.local';
        self::$named  = '@phpunit_named_' . self::$suffix . ':test.local';
        self::$noName = '@phpunit_noname_' . self::$suffix . ':test.local';

        $mUsers = new Users();
        $mUsers->insert(['user_id' => self::$named,  'password' => 'x', 'cdate' => time()]);
        $mUsers->insert(['user_id' => self::$noName, 'password' => 'x', 'cdate' => time()]);
        $mUsers->Update(['name' => 'Алиса', 'user_id' => self::$named], 'user_id = :user_id');

        (new Rooms())->insert([
            'room_id'   => self::$roomId,
            'name'      => 'DN room ' . self::$suffix,
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
        (new EventJson())->delete('room_id = :room_id')->execute([':room_id' => self::$roomId]);
        (new Events())->delete('room_id = :room_id')->execute([':room_id' => self::$roomId]);
        (new RoomMemberships())->delete('room_id = :room_id')->execute([':room_id' => self::$roomId]);
        (new Rooms())->delete('room_id = :room_id')->execute([':room_id' => self::$roomId]);
        (new Users())->delete('user_id = :user_id')->execute([':user_id' => self::$named]);
        (new Users())->delete('user_id = :user_id')->execute([':user_id' => self::$noName]);
    }

    protected function tearDown(): void
    {
        $_POST = [];
    }

    private function contentOf(string $eventId): array
    {
        $mJson = new EventJson();
        $mJson->select()->from()->where('event_id = :event_id');
        $row = $mJson->fetch(['event_id' => $eventId]);

        return json_decode($row['json'], true);
    }

    private function sendText(string $sender, string $body, ?string $replyTo = null): string
    {
        $_POST = ['room_id' => self::$roomId, 'msgtype' => 'm.text', 'body' => $body];
        if ($replyTo !== null) {
            $_POST['reply_to'] = $replyTo;
        }

        $res = json_decode((new Events())->create($sender), true);

        return $res['event_id'];
    }

    #[TestDox('m.room.message от пользователя с name несёт content.displayname = name')]
    public function testMessageEmbedsDisplayNameWhenSet(): void
    {
        $eventId = $this->sendText(self::$named, 'привет');

        self::assertSame('Алиса', $this->contentOf($eventId)['content']['displayname']);
    }

    #[TestDox('m.room.message от пользователя без name несёт content.displayname = user_id')]
    public function testMessageDisplayNameFallsBackToUserId(): void
    {
        $eventId = $this->sendText(self::$noName, 'привет');

        self::assertSame(self::$noName, $this->contentOf($eventId)['content']['displayname']);
    }

    #[TestDox('Ответ на сообщение несёт reply_to.displayname автора оригинала')]
    public function testReplyEmbedsOriginalSenderDisplayName(): void
    {
        $originalId = $this->sendText(self::$named, 'оригинал');
        $replyId = $this->sendText(self::$noName, 'ответ', $originalId);

        self::assertSame('Алиса', $this->contentOf($replyId)['content']['reply_to']['displayname']);
    }

    #[TestDox('Events::invite() записывает в m.room.member content.displayname приглашаемого')]
    public function testInviteEmbedsInviteeDisplayName(): void
    {
        $invitee = '@phpunit_dn_invitee_' . self::$suffix . ':test.local';
        (new Users())->insert(['user_id' => $invitee, 'password' => 'x', 'cdate' => time()]);
        (new Users())->Update(['name' => 'Боб', 'user_id' => $invitee], 'user_id = :user_id');

        (new Events())->invite(self::$roomId, $invitee, self::$named);

        $mMemberships = new RoomMemberships();
        $member = $mMemberships->getRoomMember(self::$roomId, $invitee);
        $content = $this->contentOf($member['event_id']);

        self::assertSame('Боб', $content['content']['displayname']);

        (new RoomMemberships())->delete('user_id = :user_id')->execute([':user_id' => $invitee]);
        (new Users())->delete('user_id = :user_id')->execute([':user_id' => $invitee]);
    }

    #[TestDox('Rooms::joinPublicRoom() записывает в m.room.member content.displayname вступающего')]
    public function testJoinPublicRoomEmbedsJoinerDisplayName(): void
    {
        $joiner = '@phpunit_dn_joiner_' . self::$suffix . ':' . WCO::$domain;
        (new Users())->insert(['user_id' => $joiner, 'password' => 'x', 'cdate' => time()]);
        (new Users())->Update(['name' => 'Ева', 'user_id' => $joiner], 'user_id = :user_id');

        (new Rooms())->joinPublicRoom(self::$roomId, $joiner);

        $member = (new RoomMemberships())->getRoomMember(self::$roomId, $joiner);
        $content = $this->contentOf($member['event_id']);

        self::assertSame('Ева', $content['content']['displayname']);

        (new RoomMemberships())->delete('user_id = :user_id')->execute([':user_id' => $joiner]);
        (new Users())->delete('user_id = :user_id')->execute([':user_id' => $joiner]);
    }
}
