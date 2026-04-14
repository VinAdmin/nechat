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
        $mEventJson = new Events();
        
        header('Content-Type: application/json');
        echo $mEventJson->sync();
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
    
    public function actionJoined_rooms() {
        $mRooms = new Rooms();
        
        header('Content-Type: application/json');
        echo $mRooms->joinedRooms();
        
        return true;
    }
    
    public function actionRooms() {
        $mEvents = new Events();
        
        header('Content-Type: application/json');
        echo $mEvents->create();
        
        return true;
    }
}
