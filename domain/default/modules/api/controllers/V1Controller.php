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

        $rawInput = file_get_contents("php://input");
        $jsonData = json_decode($rawInput, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
            $this->data = $jsonData;
        } elseif (!empty($_POST)) {
            $this->data = $_POST;
        } else {
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
        //Проверка токена доступа.
        $mAccesToken = new AccessToken();

        if (!$mAccesToken->getToken()) {
            //Ошибка если токен не валидный
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            
            return true;
        }
        
        //Получение списка комнат пользователя
        $mRooms = new Rooms();
        
        header('Content-Type: application/json');
        echo $mRooms->joinedRooms($mAccesToken->sender);
        
        return true;
    }
    
    public function actionPublicRooms() {
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            return true;
        }
        
        $query = isset($_GET['q']) ? strip_tags($_GET['q']) : '';
        
        $mRooms = new Rooms();
        
        header('Content-Type: application/json');
        echo $mRooms->searchPublicRooms($query, $mAccesToken->sender);
        
        return true;
    }
    
    public function actionProfile() {
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            return true;
        }

        header('Content-Type: application/json');

        if($_SERVER['REQUEST_METHOD'] === 'GET'){
            $mUsers = new Users();
            $user = $mUsers->getUserById($mAccesToken->sender);
            echo json_encode([
                'user_id'   => $user['user_id'] ?? '',
                'name'      => $user['name'] ?? '',
                'avatar_url' => $user['avatar_url'] ?? '',
                'email'     => $user['email'] ?? ''
            ]);
            return true;
        }

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $rawInput = file_get_contents("php://input");
            $data = json_decode($rawInput, true);

            if(!is_array($data)){
                $data = $_POST;
            }

            $avatarUrl = $data['avatar_url'] ?? '';

            if(!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK){
                $uploadDir = __DIR__ . '/../../../../../web/default/uploads';
                if(!is_dir($uploadDir)){
                    http_response_code(500);
                    echo json_encode(["error" => "Upload directory not found"]);
                    return true;
                }

                $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if(!in_array($ext, $allowedExts)){
                    echo json_encode(["error" => "Invalid file type"]);
                    return true;
                }

                $uniqueName = time() . '_' . bin2hex(random_bytes(8)) . '_avatar.' . $ext;
                $destination = $uploadDir . '/' . $uniqueName;

                if(move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)){
                    $avatarUrl = '/f/' . $uniqueName;
                }
            }

            $mUsers = new Users();

            if(!empty($data['new_password'])){
                echo $mUsers->changePassword(
                    $mAccesToken->sender,
                    $data['old_password'] ?? '',
                    $data['new_password']
                );
                return true;
            }

            echo $mUsers->updateProfile($mAccesToken->sender, [
                'avatar_url' => $avatarUrl
            ]);
            return true;
        }

        return true;
    }

    public function actionLogout() {
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            return true;
        }

        $token = str_replace("Bearer ", "", getallheaders()['Authorization']);
        $mAccesToken->deleteToken($token);

        header('Content-Type: application/json');
        echo json_encode(["status" => "ok"]);
        return true;
    }

    public function actionJoinRoom() {
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            return true;
        }
        
        $roomId = isset($this->data['room_id']) ? strip_tags($this->data['room_id']) : '';
        if(!$roomId){
            http_response_code(400);
            echo json_encode(["error" => "Room ID is required"]);
            return true;
        }
        
        $mRooms = new Rooms();
        
        header('Content-Type: application/json');
        echo $mRooms->joinPublicRoom($roomId, $mAccesToken->sender);
        
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
            $allowed = ['members', 'invite', 'accept', 'ban', 'unban', 'update', 'upload_avatar'];
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
    
    /**
     * Принятие приглашения в комнату.
     * 
     * @param array $params [roomId, sender]
     * @return string
     */
    private function accept(array $params): string {
        if(!isset($params['roomId'])){
            return json_encode(['error' => 'Not room']);
        }

        if(!isset($params['sender'])){
            return json_encode(['error' => 'Not sender']);
        }

        $mRoomMemberships = new RoomMemberships();
        $updateResult = $mRoomMemberships->Update([
            'membership' => 'join',
            'room_id' => $params['roomId'],
            'user_id' => $params['sender']
        ], 'room_id = :room_id AND user_id = :user_id');

        if (!$updateResult) {
            return json_encode(['error' => 'Unable to accept invite']);
        }

        return json_encode(['status' => 'ok']);
    }

    /**
     * Банит пользователя в комнате.
     * 
     * @param array $params [roomId, sender]
     * @return string
     */
    private function ban(array $params): string {
        if(!isset($params['roomId'])){
            return json_encode(['error' => 'Not room']);
        }

        if(!isset($params['sender'])){
            return json_encode(['error' => 'Not sender']);
        }

        if(count($this->data) === 0 || !isset($this->data['user_id'])){
            return json_encode(['error' => 'Not user_id']);
        }

        $mFilter = new Filter();
        $userId = $mFilter->string($this->data['user_id']);

        $mRoomMemberships = new RoomMemberships();
        $updateResult = $mRoomMemberships->Update([
            'membership' => 'ban',
            'room_id' => $params['roomId'],
            'user_id' => $userId
        ], 'room_id = :room_id AND user_id = :user_id');

        if (!$updateResult) {
            return json_encode(['error' => 'Unable to ban user']);
        }

        return json_encode(['status' => 'ok']);
    }

    /**
     * Снимает бан с пользователя в комнате.
     * 
     * @param array $params [roomId, sender]
     * @return string
     */
    private function unban(array $params): string {
        if(!isset($params['roomId'])){
            return json_encode(['error' => 'Not room']);
        }

        if(!isset($params['sender'])){
            return json_encode(['error' => 'Not sender']);
        }

        if(count($this->data) === 0 || !isset($this->data['user_id'])){
            return json_encode(['error' => 'Not user_id']);
        }

        $mFilter = new Filter();
        $userId = $mFilter->string($this->data['user_id']);

        $mRoomMemberships = new RoomMemberships();
        $updateResult = $mRoomMemberships->Update([
            'membership' => 'join',
            'room_id' => $params['roomId'],
            'user_id' => $userId
        ], 'room_id = :room_id AND user_id = :user_id');

        if (!$updateResult) {
            return json_encode(['error' => 'Unable to unban user']);
        }

        return json_encode(['status' => 'ok']);
    }

    /**
     * Обновляет настройки комнаты (название, тема, аватар, правила входа).
     *
     * @param array $params [roomId, sender]
     * @return string
     */
    private function update(array $params): string {
        if(!isset($params['roomId'])){
            return json_encode(['error' => 'Not room']);
        }

        if(!isset($params['sender'])){
            return json_encode(['error' => 'Not sender']);
        }

        $mRooms = new Rooms();
        return $mRooms->updateRoom($params['sender']);
    }

    /**
     * Загружает аватар комнаты.
     *
     * @param array $params [roomId, sender]
     * @return string
     */
    private function upload_avatar(array $params): string {
        if(!isset($params['roomId'])){
            return json_encode(['error' => 'Not room']);
        }

        if(!isset($params['sender'])){
            return json_encode(['error' => 'Not sender']);
        }

        $mRooms = new Rooms();
        $room = $mRooms->getRoomId($params['roomId']);
        if(!isset($room['room_id']) || $room['creator'] !== $params['sender']){
            return json_encode(['error' => 'Only the room creator can change the avatar']);
        }

        if(empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK){
            return json_encode(['error' => 'File upload error']);
        }

        $uploadDir = __DIR__ . '/../../../../../web/default/uploads';
        if(!is_dir($uploadDir)){
            return json_encode(['error' => 'Upload directory not found']);
        }

        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if(!in_array($ext, $allowedExts)){
            return json_encode(['error' => 'Invalid file type. Allowed: jpg, png, gif, webp']);
        }

        $uniqueName = time() . '_' . bin2hex(random_bytes(8)) . '_avatar.' . $ext;
        $destination = $uploadDir . '/' . $uniqueName;

        if(!move_uploaded_file($_FILES['file']['tmp_name'], $destination)){
            return json_encode(['error' => 'Unable to save file']);
        }

        $fileUrl = '/f/' . $uniqueName;

        return json_encode(['status' => 'ok', 'file_url' => $fileUrl]);
    }
}
