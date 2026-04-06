<?php

namespace app\models;

use wco\db\DB;
use wco\kernel\WCO;
use app\models\AccessToken;

/**
 * Описание класса: Работа c пользователем.
 *
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class Users extends DB{
    function __construct() {
        parent::__construct();
    }

    public function init() {
        return 'users';
    }
    
    public function checkUser($user_id): bool {
        $this->select()->form()->where("user_id = :user_id");
        $result = $this->fetch(['user_id' => $user_id]);
        
        if(isset($result['user_id'])){
            return true;
        }
        
        return false;
    }
    
    public function registration(): string {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $user_id = "@" . strip_tags($data['login']). ":" . WCO::$domain;
        $password = strip_tags($data['password']);
        
        if(!$this->checkUser($user_id)){
            $password = password_hash($password, PASSWORD_BCRYPT);
            $this->insert([
                'user_id'  => $user_id ,
                'password' => $password,
                'cdate'    => time()
            ]);

            return json_encode(["status" => "ok"]);
        }
        
        return json_encode(["error" => "A user with this name already exists."]);
    }
    
    public function authorization() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if(!isset($data['login'])){
            http_response_code(401);
            return json_encode(["error" => "Invalid data"]);
        }
        
        $user_id = "@" . strip_tags($data['login']). ":" . WCO::$domain;
        $password = strip_tags($data['password']);
        
        $this->select()->form()->where("user_id = :user_id");
        $result = $this->fetch(['user_id' => $user_id]);
        
        if ($result) {
            if (!password_verify($password, $result['password'])) {
                return json_encode(["error" => "Incorrect login or password"]);
            }
        }
        
        $mAccessToken = new AccessToken();
        $token = $mAccessToken->createToken($result['id']);
        
        return json_encode([
            "status"  => "ok",
            'user_id' => $result['user_id'],
            'token'   => $token
        ]);
    }
}
