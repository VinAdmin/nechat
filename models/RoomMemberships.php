<?php
namespace app\models;

use wco\db\DB;
use wco\kernel\WCO;
use app\models\AccessToken;

/**
 * Description of RoomMemberships
 *
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class RoomMemberships extends DB{
    const IN_MEMBERSHIP = "membership IN ('join','invite')";

    function __construct() {
        parent::__construct();
    }

    public function init() {
        return 'room_memberships';
    }
    
    /**
     * Добавляет пользователя а комнату.
     * 
     * @param array $col [event_id, user_id, sender, room_id membership]
     * @return array
     */
    public function addUser(array $col): array{
        $this->insert($col);
        return [];
    }
    
    /**
     * @param string $sender
     * @return array [event_id, user_id, sender, room_id, membership]
     */
    public function getMember(string $sender): array {
        $membership = "membership IN ('join','invite')";
        
        $this->select()->from()->where("user_id = :user_id AND $membership");
        $res = $this->fetch(['user_id' => $sender]);
        
        if(!$res){
           $res = []; 
        }
        
        return $res;
    }
    
    /**
     * Возвращает список участников комнаты
     * 
     * @param string $room_id
     * @return array [event_id, user_id, sender, room_id, membership]
     */
    public function getRoomMembers(string $room_id): array {
        $this->select()->from()
                ->where("room_id = :room_id");
        
        $res = $this->fetchAll(['room_id' => $room_id]);
        
        if(count($res) < 0){
           $res = []; 
        }
        
        return $res;
    }
}
