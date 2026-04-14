<?php
namespace app\models;

use wco\db\DB;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use app\models\Users;

/**
 * Description of AccessTolen
 *
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class AccessToken extends DB{
    public $sender = '';
            
    function __construct() {
        parent::__construct();
    }

    public function init() {
        return 'access_tokens';
    }
    
    /**
     * @param string $user_id
     * @return string
     */
    public function createToken(string $user_id): string {
        $payload = [
            "user_id" => $user_id,
            "exp" => time() + 3600 // 1 час
        ];

        $jwt = JWT::encode($payload, SECRET_KEY, 'HS256');

        $this->insert([
            'user_id' => $user_id ,
            'token'   => $jwt,
            'cdate'   => time()
        ]);
        
        return $jwt;
    }
    
    public function getToken() {
        $headers = getallheaders();
        
        if (!isset($headers['Authorization'])) {
            return false;
        }
        
        $token = str_replace("Bearer ", "", $headers['Authorization']);
        $token = trim(strip_tags($token));
        $mUsers = new Users();
        
        $this->select()->form()->joinInner(['u' => $mUsers->init()], "u.user_id = t1.user_id")->where('token = :token');
        $result = $this->fetch(['token' => $token]);
        
        if(isset($result['token'])){
            $this->sender = $result['user_id'];
            
            return true;
        }
        
        return false;
    }
}
