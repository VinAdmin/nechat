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
     * Добавляет событие в таблицу `events`.
     *
     * Ожидает в $params поля: 'type', 'room_id', 'sender'.
     * Возвращает сгенерированный идентификатор события.
     * Внимание: здесь используется UUID для event_id.
     *
     * @param array $params [type, room_id, sender]
     * @return string
     */
    public function addEvent($params): string {
        $uuid = Uuid::uuid4()->toString();
        // Идентификатор события (строка UUID)
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
        
        // Создаём событие сообщения в комнате
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
        
        // Сохраняем JSON-представление события в отдельной таблице
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
     * Возвращает события (с учётом членства) для синхронизации фронтенда.
     * @param string $sender Текущий пользователь (sender) — пока не используется напрямую
     * @return string JSON с комнатами и событиями
     */
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
        $arr['rooms']['join'] = [];
        $arr['next_batch'] = 0;
        
        $i = 0;
        // Формируем структуру ответа: rooms.join[room_id].events[]
        foreach ($result as $key => $event){
            /*if($event['membership'] === 'invite'){
                $arr[$key]['rooms']['invite'][$event['room_id']] = [];
                //continue;
            }*/
            
            // Преобразуем JSON из БД в объект для фронтенда
            $arr['rooms']['join'][$event['room_id']]['events'][] = [
                'event_id' => $event['event_id'],
                'json' => json_decode($event['json'])
            ];
            
            if($arr['next_batch'] < $event['received_ts']){
                $arr['next_batch'] = $event['received_ts'];
            }
            $i++;
        }
        
        return json_encode($arr);
    }
    
    /**
     * Создаёт приглашение или меняет статус членства пользователя в комнате.
     *
     * @param string $roomId id комнаты
     * @param string $userId id пользователя (@user:domain)
     * @param string $sender кто отправил приглашение
     * @param string $membership 'invite' или 'join'
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

        // Формируем тело события m.room.member с полем membership
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
