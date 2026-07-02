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
    // Условие для выборки активных членств
    const IN_MEMBERSHIP = "membership IN ('join','invite')";

    function __construct() {
        parent::__construct();
    }

    // Возвращает имя таблицы модели
    public function init() {
        return 'room_memberships';
    }
    
    /**
     * Добавляет запись о членстве пользователя в комнате.
     * Ожидается массив с ключами: event_id, user_id, sender, room_id, membership
     *
     * @param array $col
     * @return array Возвращаем пустой массив (плейсхолдер)
     */
    public function addUser(array $col): array{
        $this->insert($col);
        return [];
    }
    
    /**
     * Возвращает запись членства по user_id (если есть активное членство).
     *
     * @param string $sender user_id
     * @return array [event_id, user_id, sender, room_id, membership] или []
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
     * Возвращает список участников для заданной комнаты.
     *
     * @param string $room_id
     * @return array Список записей членства
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
    
    /**
     * Возвращает запись участника комнаты по room_id и user_id.
     *
     * @param string $room_id
     * @param string $userId
     * @return array Запись или пустой массив
     */
    public function getRoomMember(string $room_id, string $userId): array {
        $this->select()->from()
                ->where("room_id = :room_id AND user_id = :user_id");

        $res = $this->fetch([
            'room_id' => $room_id,
            'user_id' => $userId
        ]);

        if(isset($res['user_id'])){
           return $res;
        }

        $res = [];
        return $res;
    }
}
