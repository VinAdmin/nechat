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
}
