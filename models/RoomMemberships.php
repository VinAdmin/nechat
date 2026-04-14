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
     * @return array
     */
    public function getMember(string $sender): array {
        $membership = "membership IN ('join','invite')";
        
        $this->select()->form()->where("user_id = :user_id AND $membership");
        $res = $this->fetch(['user_id' => $sender]);
        
        if(!$res){
           $res = []; 
        }
        
        return $res;
    }
}
