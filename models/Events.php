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
        
        if(isset($data['msgtype'])){
            $type = strip_tags($data['msgtype']);
        }
        
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
            'content' => [
                'body'    => $data['body'],
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
    
    public function sync(string $sender): string {
        $since = filter_input(INPUT_GET, 'since');
        $since = strip_tags($since);
        
        $mEventJson = new EventJson();
        $mRoomMemberships = new RoomMemberships();
        
        $sql = $this->select("*")->from();
        $sql->joinInner(['ej' => $mEventJson->init()], "ej.event_id = t1.event_id");
        $sql->joinInner(['m' => $mRoomMemberships->init()], "m.room_id = t1.room_id");
        
        $membership = "m.membership IN ('join', 'invite')";
        if($since){
            $sql->where("received_ts > $since AND $membership");
        }else{
            $sql->where("$membership");
        }
        
        $sql->order_by('received_ts ASC')->limit(1000);
        
        $result = $this->fetchAll();
        $arr = [];
        
        $i = 0;
        foreach ($result as $event){
            if($event['membership'] === 'invite'){
                $arr[$i]['rooms']['invite'][$event['room_id']] = [];
                continue;
            }
            
            $arr[$i] = $event;
            $arr[$i]['json'] = json_decode($event['json']);
            
            $i++;
        }
        
        return json_encode($arr);
    }
}
