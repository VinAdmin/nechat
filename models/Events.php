<?php

namespace app\models;

use wco\db\DB;
use wco\kernel\WCO;
use app\models\AccessToken;
use Ramsey\Uuid\Uuid;
use app\models\EventJson;
use app\models\Rooms;

/**
 * Description of Events
 *
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class Events extends DB{
    function __construct() {
        parent::__construct();
    }

    public function init() {
        return 'events';
    }
    
    /**
     * Добавляет событие.
     * 
     * @param array $params [type, room_id, sender]
     * @return string
     */
    public function addEvent($params): string {
        $uuid = Uuid::uuid4()->toString();
        $eventId = "$$uuid";
        
        $this->insert([
            'event_id'    => $eventId,
            'type'        => $params['type'],
            'room_id'     => $params['room_id'],
            'sender'      => $params['sender'],
            'received_ts' => time()
        ]);
        
        return $eventId;
    }
    
    public function create($sender) {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if(!isset($data['room_id'])){
            http_response_code(401);
            return json_encode(["error" => "Room not found"]);
        }
        
        if(!isset($data['msgtype'])){
            http_response_code(401);
            return json_encode(["error" => "Message type not specified"]);
        }
        $type = strip_tags($data['msgtype']);
        
        if(!isset($data['body'])){
            http_response_code(401);
            return json_encode(["error" => "Body error"]);
        }
        $body = strip_tags($data['body']);
        
        $room_id = strip_tags($data['room_id']);
        $mRooms = new Rooms();
        $room = $mRooms->getRoomId($room_id);
        
        if(!isset($room['room_id'])){
            http_response_code(401);
            return json_encode(["error" => "Room not found"]);
        }
        
        $eventId = $this->addEvent([
            'type'    => $type,
            'room_id' => $room['room_id'],
            'sender'  => $sender,
        ]);
        
        $json = json_encode([
            'event_id' => $eventId,
            'type'     => $type,
            'room_id'  => $room['room_id'],
            'sender'   => $sender,
            'origin_server_ts' => round(microtime(true) * 1000),
            'content' => [
                'body'    => $body,
                'room_id' => $room['room_id'],
                'sender'  => $sender
            ]
        ]);
        
        $mEventJson = new EventJson();
        $mEventJson->add([
            'event_id' => $eventId,
            'room_id'  => $room['room_id'],
            'json'     => $json
        ]);
        
        return json_encode([
            'status'  => 'ok',
            'event_id' => $eventId
        ]);
    }
    
    /**
     * Возвращает список событий для заданного пользователя.
     *
     * @param string $sender
     * @return string Список событий
     */
    public function sync(string $sender): string {
        $since = filter_input(INPUT_GET, 'since', FILTER_VALIDATE_INT);

        if ($since === false) {
            $since = null;
        }
        
        $mEventJson = new EventJson();
        $mRoomMemberships = new RoomMemberships();
        
        $sql = $this->select("*")->from();
        $sql->joinInner(['ej' => $mEventJson->init()], "ej.event_id = t1.event_id");
        $sql->joinInner(['m' => $mRoomMemberships->init()], "m.room_id = t1.room_id AND m.user_id = :sender AND m.membership IN ('join','invite')");
        
        $params = ['sender' => $sender];
        if($since !== null){
            $sql->where("t1.received_ts > :since");
            $params['since'] = $since;
        }
        
        $sql->order_by('received_ts ASC')->limit(1000);
        
        $result = $this->fetchAll($params);
        
        $arr = [];
        $arr['next_batch'] = 0;
        
        foreach ($result as $key => $event){
            if($event['membership'] === 'invite'){                          // Если это приглашение, то добавляем в массив invite
                $arr['rooms']['invite'][$event['room_id']] = [];
            }

            if($event['membership'] === 'join'){                            // Если это присоединение, то добавляем в массив join
                $arr['rooms']['join'][$event['room_id']]['events'][] = [
                    'event_id' => $event['event_id'],
                    'json' => json_decode($event['json'], true)
                ];
            }

            if($arr['next_batch'] < $event['received_ts']){                 // Если текущий next_batch меньше, чем received_ts события, то обновляем next_batch
                $arr['next_batch'] = $event['received_ts'];
            }
        }
        
        return json_encode($arr);
    }
    
    /**
     * @param string $roomId
     * @param string $userId
     * @param string $sender
     * @param string $membership По умолчанию invite
     * @return bool
     */
    public function invite(string $roomId, string $userId, string $sender, string $membership = 'invite'): bool {
        $mRoomMemberships = new RoomMemberships();
        $member = $mRoomMemberships->getRoomMember($roomId, $userId);
        
        $type = 'm.room.member';

        $eventId = $this->addEvent([
            'type'    => $type,
            'room_id' => $roomId,
            'sender'  => $sender,
        ]);

        $mEventJson = new EventJson();
        
        $displayname = str_replace(['@', ':'.WCO::$domain], ['', ''], $userId);

        $json = json_encode([
            'type'   => $type,
            'sender' => $sender,
            'content' => [
                'displayname' => $displayname,
                'membership'  => $membership
            ]
        ]);

        $mEventJson->add([
            'event_id' => $eventId,
            'room_id'  => $roomId,
            'json'     => $json
        ]);

        $mRoomMemberships->addUser([
            'event_id'   => $eventId,
            'user_id'    => $userId,
            'sender'     => $sender,
            'room_id'    => $roomId,
            'membership' => $membership
        ]);

        return true;
    }
}
