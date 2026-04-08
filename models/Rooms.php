<?php
namespace app\models;

use wco\db\DB;
use wco\kernel\WCO;
use app\models\AccessToken;
use Ramsey\Uuid\Uuid;

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
    
    public function createRoom(): string {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            return json_encode(["error" => "\"Invalid token\" error"]);
        }
        
        if(!isset($data['name'])){
            http_response_code(401);
            return json_encode(["error" => "?"]);
        }
        $name = strip_tags($data['name']);
        
        $uuid = Uuid::uuid4()->toString();
        $random = $uuid;
        
        if($name){
            $this->insert([
                'room_id' => "!$random:" . WCO::$domain,
                'name'    => $name,
                'cdate'   => time()
            ]);
            
            return json_encode(["status" => "ok"]);
        }
        
        return json_encode(["error" => "Unknown error"]);
    }
    
    public function joinedRooms() {
        $mAccesToken = new AccessToken();

        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            return json_encode(["error" => "\"Invalid token\" error"]);
        }
        
        $this->select()->form();
        return json_encode($this->fetchAll());
    }
    
    public function getRoomId(string $roomId): array {
        $this->select()->form()->where("room_id = :room_id");
        $result = $this->fetch(['room_id' => $roomId]);
        
        return $result;
    }
}
