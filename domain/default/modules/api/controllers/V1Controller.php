<?php
use wco\kernel\WCO;
use app\models\Users;
use app\models\Rooms;
use app\models\Events;
use app\models\EventJson;
use app\models\AccessToken;
use app\models\RoomMemberships;
use app\models\Filter;

/**
 * API V1
 * 
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class V1Controller extends \wco\kernel\Controller{
    protected $data = [];

    function __construct() {
        parent::__construct();
        $this->data = json_decode(file_get_contents("php://input"), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->data = [];
        }
    }

    public function actionIndex() {
        
        return true;
    }
    
    public function actionSync() {
        $mAccesToken = new AccessToken();
        
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            return true;
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
    
    private function decodeUriRoom(): array {
        $uri = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL);
        
        $arr = [];
        $members = '';
        
        if($uri){
            $params = explode('/', $uri);
            $params = array_diff($params, array(''));
            
            //$url[4] - room_id
            if(isset($params[4])){
                $arr['room_id'] = Filter::string($params[4]);
            }
            
            if(isset($params[5])){
                $members = Filter::string($params[5]);
                $arr['members'] = $members;
            }
                
            return $arr;
        }
        
        return $arr;
    }
    
    /**
     * Действия с комнатами
     * 
     * @return bool
     */
    public function actionRooms() {
        header('Content-Type: application/json');
        
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
        
        $members = $this->decodeUriRoom();
        if(count($members) > 0 && isset($members['room_id'])){
            if(count($mRooms->getRoomId($members['room_id'])) === 0){
                //Вывод ошибки если не удалось найти комнату.
                http_response_code(401);
                echo json_encode(['error' => 'The requested room was not found.']);
                
                return true;
            }
            
            //Поиск функции контроллера.
            $allowed = ['members', 'invite'];
            if(in_array($members['members'], $allowed)){
                $data = [
                    'roomId' => $members['room_id'],
                    'sender' => $mAccesToken->sender,
                ];
                
                echo $this->{$members['members']}($data);
                
                return true;
            }
        }
        
        $mEvents = new Events();
        echo $mEvents->create($mAccesToken->sender);
        
        return true;
    }
    
    /**
     * Участники.
     * @param array $params
     * @return string
     */
    private function members(array $params): string {
        if(!isset($params['roomId'])){
            return json_encode(['error' => 'Not room']);
        }
        
        $mRoomMemberships = new RoomMemberships();
        $mem = $mRoomMemberships->getRoomMembers($params['roomId']);
        
        return json_encode($mem);
    }
    
    /**
     * Создает запрос на приглашение
     * 
     * @param array $params [roomId, sender]
     * @return string
     */
    private function invite(array $params): string {
        if(count($this->data) === 0){
            return json_encode(['error' => 'Incorrect request']);
        }
        
        if(!isset($params['roomId'])){
            return json_encode(['error' => 'Not room']);
        }
        
        if(!isset($params['sender'])){
            return json_encode(['error' => 'Not sender']);
        }
        
        if(!isset($this->data['user_id'])){
            return json_encode(['error' => 'Not user_id']);
        }
        
        $mFilter = new Filter();
        $userId = $mFilter->string($this->data['user_id']);
        
        $mUser = new Users();
        if(!$mUser->checkUser($userId)){
            return json_encode(['error' => 'Unable to find user']);
        }
        
        $targetUserId = $mUser->getUserId();
        
        if(!isset($member['user_id'])){
            $modelEvent = new Events();
            $modelEvent->invite($params['roomId'], $targetUserId, $params['sender']);
        }
        
        return json_encode(['status' => 'ok']);
    }
}
