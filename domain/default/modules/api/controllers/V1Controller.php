<?php
use wco\kernel\WCO;
use app\models\Users;
use app\models\Rooms;
use app\models\Events;
use app\models\EventJson;
use app\models\AccessToken;

/**
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class V1Controller extends \wco\kernel\Controller{
    public function actionIndex() {
        
        return true;
    }
    
    public function actionSync() {
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            return json_encode(["error" => "\"Invalid token\" error"]);
        }
        
        $mEventJson = new Events();
        
        header('Content-Type: application/json');
        echo $mEventJson->sync($mAccesToken->sender);
        return true;
    }
    
    public function actionRegistration() {
        $mUser = new Users();
        
        header('Content-Type: application/json');
        echo $mUser->registration();
        
        return true;
    }
    
    public function actionAuthorization() {
        $mUser = new Users();
        
        header('Content-Type: application/json');
        echo $mUser->authorization();
        
        return true;
    }
    
    public function actionCreateRoom() {
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            return true;
        }
        
        $mRooms = new Rooms();
        
        header('Content-Type: application/json');
        echo $mRooms->createRoom($mAccesToken->sender);
        
        return true;
    }
    
    /**
     * Возвращает список текущих комнат пользователя.
     * 
     * @return bool
     */
    public function actionJoined_rooms() {
        $mAccesToken = new AccessToken();

        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            
            return true;
        }
        
        $mRooms = new Rooms();
        
        header('Content-Type: application/json');
        echo $mRooms->joinedRooms($mAccesToken->sender);
        
        return true;
    }
    
    public function actionRooms() {
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            
            return true;
        }
        
        $mRooms = new Rooms();
        
        if(!$mRooms->accessRoom($mAccesToken->sender)){
            http_response_code(401);
            echo json_encode(["error" => "Messages are not allowed in this room."]);
            
            return true;
        }
        
        $mEvents = new Events();
        
        header('Content-Type: application/json');
        echo $mEvents->create($mAccesToken->sender);
        
        return true;
    }
}
