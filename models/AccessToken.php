<?php
namespace app\models;

use wco\db\DB;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Description of AccessTolen
 *
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class AccessToken extends DB{
    function __construct() {
        parent::__construct();
    }

    public function init() {
        return 'access_tokens';
    }
    
    public function createToken($user_id) {
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
        
        $this->select()->form()->where('token = :token');
        $result = $this->fetch(['token' => $token]);
        
        if(isset($result['token'])){
            return true;
        }
        
        return false;
    }
}
