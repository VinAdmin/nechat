<?php

namespace app\models;

use wco\db\DB;
use wco\kernel\WCO;
use app\models\AccessToken;

/**
 * Описание класса: Работа c пользователем.
 * 
 * @property string $user_id по умолчанию null
 *
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class Users extends DB{
    private $user_id = null;
            
    function __construct() {
        parent::__construct();
    }

    public function init() {
        return 'users';
    }
    
    /**
     * Проверка пользователя в базе данных.
     * 
     * @param string $user_id
     * @return bool true, false
     */
    public function checkUser(string $user_id): bool {
       $user_id = strip_tags($user_id);
        
        $this->select()->from()->where("user_id = :user_id");
        $result = $this->fetch(['user_id' => $user_id]);
        
        if(isset($result['user_id'])){
            $this->user_id = $result['user_id'];
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Возвращает из базы данных ид пользователя.
     * 
     * @return string Если не было запроса для БД значение по умолчанию null
     */
    public function getUserId(): string | null {
        return $this->user_id;
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
        
        $this->select()->from()->where("user_id = :user_id");
        $result = $this->fetch(['user_id' => $user_id]);
        
        if (!$result) {
            return json_encode(["error" => "Incorrect login or password"]);
        }
        
        if (!password_verify($password, $result['password'])) {
            return json_encode(["error" => "Incorrect login or password"]);
        }
        
        $mAccessToken = new AccessToken();
        $token = $mAccessToken->createToken($result['user_id']);
        
        if(!$token){
            http_response_code(401);
            return json_encode(["error" => "Unable to obtain a token"]);
        }
        
        $this->user_id = $result['user_id'];
        
        return json_encode([
            "status"  => "ok",
            'user_id' => $result['user_id'],
            'token'   => $token
        ]);
    }
}
