<?php
use wco\kernel\WCO;
use app\models\Users;
use app\models\Rooms;

/**
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class V1Controller extends \wco\kernel\Controller{
    public function actionIndex() {
        
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
        $mRooms = new Rooms();
        
        header('Content-Type: application/json');
        echo $mRooms->createRoom();
        
        return true;
    }
    
    public function actionJoined_rooms() {
        $mRooms = new Rooms();
        
        header('Content-Type: application/json');
        echo $mRooms->joinedRooms();
        
        return true;
    }
}
