<?php

namespace app\models;

use wco\db\DB;
use wco\kernel\WCO;

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
}
