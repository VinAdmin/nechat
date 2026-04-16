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
        $uuid = Uuid::uuid4()->toString();
        $room_id = "!$uuid:" . WCO::$domain; //Формируем id комнаты
        
        if($name){
            $this->insert([
                'room_id' => $room_id,
                'name'    => $name,
                'creator' => $sender,
                'cdate'   => time()
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
                ->where("m.user_id = :user_id AND m.membership IN ('join')");
        return json_encode($this->fetchAll(['user_id' => $sender]));
    }
    
    public function getRoomId(string $roomId): array {
        $result = [];
        $this->select()->from()->where("room_id = :room_id");
        $result = $this->fetch(['room_id' => $roomId]);
        
        if(!empty($result)){
            return $result;
        }
        
        return $result = [];
    }
}
