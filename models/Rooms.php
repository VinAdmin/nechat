<?php
namespace app\models;

use wco\db\DB;
use wco\kernel\WCO;
use app\models\AccessToken;
use Ramsey\Uuid\Uuid;
use app\models\RoomMemberships;
use app\models\Events;

/**
 * Description of Rooms
 *
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class Rooms extends DB{
    function __construct() {
        parent::__construct();
    }

    public function init() {
        return 'rooms';
    }
    
    /**
     * Создание комнаты
     * 
     * @param string $sender Отправитель
     * @return string
     */
    public function createRoom(string $sender): string {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if(!isset($data['name'])){
            http_response_code(400);
            return json_encode(["error" => "Something was wrong"]);
        }
        
        $name = strip_tags($data['name']);
        $topic = isset($data['topic']) ? strip_tags($data['topic']) : '';
        $joinRule = isset($data['join_rule']) && in_array($data['join_rule'], ['public', 'invite']) ? $data['join_rule'] : 'public';
        $uuid = Uuid::uuid4()->toString();
        $room_id = "!$uuid:" . WCO::$domain;
        
        if($name){
            $this->insert([
                'room_id'   => $room_id,
                'name'      => $name,
                'topic'     => $topic,
                'join_rule' => $joinRule,
                'creator'   => $sender,
                'cdate'     => time()
            ]);
            
            $mRoomMemberships = new RoomMemberships();
            $mEvents = new Events();
            
            $eventId = $mEvents->addEvent([
                'type'    => 'create.room',
                'room_id' => $room_id,
                'sender'  => $sender
            ]);
            
            $mRoomMemberships->addUser([
                'event_id'    => $eventId,
                'user_id'     => $sender,
                'sender'      => $sender,
                'room_id'     => $room_id,
                'membership'  => 'join'
            ]);
            
            return json_encode(["status" => "ok"]);
        }
        
        return json_encode(["error" => "Unknown error"]);
    }
    
    /**
     * Обновление настроек комнаты.
     * 
     * @param string $sender Отправитель (проверка, что он создатель)
     * @return string
     */
    public function updateRoom(string $sender): string {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if(!isset($data['room_id'])){
            http_response_code(400);
            return json_encode(["error" => "Room ID is required"]);
        }
        
        $roomId = strip_tags($data['room_id']);
        $room = $this->getRoomId($roomId);
        
        if(!isset($room['room_id'])){
            http_response_code(404);
            return json_encode(["error" => "Room not found"]);
        }
        
        if($room['creator'] !== $sender){
            http_response_code(403);
            return json_encode(["error" => "Only the room creator can change settings"]);
        }
        
        $update = [];
        
        if(isset($data['name'])){
            $name = strip_tags($data['name']);
            if($name){
                $update['name'] = $name;
            }
        }
        
        if(isset($data['topic'])){
            $update['topic'] = strip_tags($data['topic']);
        }
        
        if(isset($data['join_rule']) && in_array($data['join_rule'], ['public', 'invite'])){
            $update['join_rule'] = $data['join_rule'];
        }
        
        if(isset($data['avatar_url'])){
            $update['avatar_url'] = strip_tags($data['avatar_url']);
        }
        
        if(empty($update)){
            return json_encode(["status" => "ok", "message" => "Nothing to update"]);
        }
        
        $update['room_id'] = $roomId;
        $this->Update($update, 'room_id = :room_id');
        
        return json_encode(["status" => "ok"]);
    }
    
    public function accessRoom(string $sender): bool {
        $mRoomMemberships = new RoomMemberships();
        $mem = $mRoomMemberships->getMember($sender);
        
        if(!isset($mem['user_id'])){
            return false;
        }
        
        return true;
    }
    
    /**
     * Комнаты участника.
     * 
     * @param string $sender
     * @return string
     */
    public function joinedRooms(string $sender): string {
        $mRoomMemberships = new RoomMemberships();
        
        $this->select("*")->from()
                ->joinInner(['m' => $mRoomMemberships->init()], "m.room_id = t1.room_id")
                ->where("m.user_id = :user_id AND m.membership IN ('join','invite')");
        return json_encode($this->fetchAll(['user_id' => $sender]));
    }
    
    /**
     * Возвращает данные по комнате.
     * 
     * @param string $roomId
     * @return array [room_id, creator, name, topic, cdate]
     */
    public function getRoomId(string $roomId): array {
        $result = [];
        $this->select()->from()->where("room_id = :room_id");
        $result = $this->fetch(['room_id' => $roomId]);
        
        if(!empty($result)){
            return $result;
        }
        
        return $result = [];
    }
    
    /**
     * Поиск публичных комнат, в которых пользователь не состоит и не забанен.
     * 
     * @param string $query Поисковый запрос
     * @param string $sender Текущий пользователь
     * @return string JSON
     */
    public function searchPublicRooms(string $query, string $sender): string {
        $mRoomMemberships = new RoomMemberships();
        
        $this->select("t1.room_id, t1.name, t1.topic, t1.avatar_url, t1.creator, t1.cdate")
                ->from()
                ->where("t1.join_rule = 'public' AND t1.name LIKE :query
                    AND t1.room_id NOT IN (
                        SELECT m.room_id FROM {$mRoomMemberships->init()} m
                        WHERE m.user_id = :sender
                    )");
        
        $results = $this->fetchAll([
            'query'  => '%' . $query . '%',
            'sender' => $sender
        ]);
        
        return json_encode($results);
    }
    
    /**
     * Добавляет пользователя в публичную комнату (без приглашения).
     * 
     * @param string $roomId
     * @param string $sender
     * @return string
     */
    public function joinPublicRoom(string $roomId, string $sender): string {
        $room = $this->getRoomId($roomId);
        
        if(!isset($room['room_id'])){
            http_response_code(404);
            return json_encode(["error" => "Room not found"]);
        }
        
        if($room['join_rule'] !== 'public'){
            http_response_code(403);
            return json_encode(["error" => "This room is not public"]);
        }
        
        $mRoomMemberships = new RoomMemberships();
        $member = $mRoomMemberships->getRoomMember($roomId, $sender);
        
        if(isset($member['user_id'])){
            http_response_code(400);
            return json_encode(["error" => "You are already in this room"]);
        }
        
        $mEvents = new Events();
        $eventId = $mEvents->addEvent([
            'type'    => 'm.room.member',
            'room_id' => $roomId,
            'sender'  => $sender
        ]);

        $displayname = str_replace(['@', ':'.WCO::$domain], ['', ''], $sender);

        $json = json_encode([
            'type'   => 'm.room.member',
            'sender' => $sender,
            'content' => [
                'displayname' => $displayname,
                'membership'  => 'join'
            ]
        ]);

        $mEventJson = new EventJson();
        $mEventJson->add([
            'event_id' => $eventId,
            'room_id'  => $roomId,
            'json'     => $json
        ]);

        $mRoomMemberships->addUser([
            'event_id'   => $eventId,
            'user_id'    => $sender,
            'sender'     => $sender,
            'room_id'    => $roomId,
            'membership' => 'join'
        ]);
        
        return json_encode(["status" => "ok"]);
    }
}
