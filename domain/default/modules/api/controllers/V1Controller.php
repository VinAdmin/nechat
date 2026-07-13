<?php
use wco\kernel\WCO;
use app\models\Users;
use app\models\Rooms;
use app\models\Events;
use app\models\EventJson;
use app\models\AccessToken;
use app\models\RoomMemberships;
use app\models\Filter;
use app\models\UserPresence;
use app\models\TypingIndicator;

/**
 * API V1
 * 
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class V1Controller extends \wco\kernel\Controller{
    protected $data = [];
    protected $modelFilter = '';
    
    function __construct() {
        parent::__construct();
        
        $this->modelFilter = new Filter();

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
                $uploadDir = __DIR__ . '/../../../../../data/uploads';
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
            $allowed = ['members', 'invite', 'accept', 'ban', 'unban', 'kick', 'update', 'upload_avatar', 'delete'];
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

        $mEvents = new Events();
        $eventId = $mEvents->addEvent([
            'type'    => 'm.room.member',
            'room_id' => $params['roomId'],
            'sender'  => $params['sender']
        ]);

        $displayname = str_replace(['@', ':'.WCO::$domain], ['', ''], $params['sender']);

        $json = json_encode([
            'type'   => 'm.room.member',
            'sender' => $params['sender'],
            'content' => [
                'displayname' => $displayname,
                'membership'  => 'join'
            ]
        ]);

        $mEventJson = new EventJson();
        $mEventJson->add([
            'event_id' => $eventId,
            'room_id'  => $params['roomId'],
            'json'     => $json
        ]);

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

        $mRooms = new Rooms();
        $room = $mRooms->getRoomId($params['roomId']);
        if(!isset($room['room_id']) || $room['creator'] !== $params['sender']){
            return json_encode(['error' => 'Only the room creator can ban users']);
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

        $mRooms = new Rooms();
        $room = $mRooms->getRoomId($params['roomId']);
        if(!isset($room['room_id']) || $room['creator'] !== $params['sender']){
            return json_encode(['error' => 'Only the room creator can unban users']);
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
     * Выгоняет пользователя из комнаты (удаляет членство).
     * В отличие от бана, пользователь может повторно войти в публичную комнату.
     * 
     * @param array $params [roomId, sender]
     * @return string
     */
    private function kick(array $params): string {
        if(!isset($params['roomId'])){
            return json_encode(['error' => 'Not room']);
        }

        if(!isset($params['sender'])){
            return json_encode(['error' => 'Not sender']);
        }

        if(count($this->data) === 0 || !isset($this->data['user_id'])){
            return json_encode(['error' => 'Not user_id']);
        }

        $mRooms = new Rooms();
        $room = $mRooms->getRoomId($params['roomId']);
        if(!isset($room['room_id']) || $room['creator'] !== $params['sender']){
            return json_encode(['error' => 'Only the room creator can kick users']);
        }

        $mFilter = new Filter();
        $userId = $mFilter->string($this->data['user_id']);

        $mRoomMemberships = new RoomMemberships();
        $mRoomMemberships->delete("room_id = :roomId AND user_id = :userId")
                ->execute([':roomId' => $params['roomId'], ':userId' => $userId]);

        if (!$mRoomMemberships) {
            return json_encode(['error' => 'Unable to kick user']);
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

    private function delete(array $params): string {
        if(!isset($params['roomId'])){
            return json_encode(['error' => 'Not room']);
        }

        if(!isset($params['sender'])){
            return json_encode(['error' => 'Not sender']);
        }

        if(!isset($this->data['event_id'])){
            return json_encode(['error' => 'Not event_id']);
        }

        $mFilter = new Filter();
        $eventId = $mFilter->string($this->data['event_id']);

        $mEvents = new Events();
        $mEvents->select()->from()->where("event_id = :event_id");
        $event = $mEvents->fetch(['event_id' => $eventId]);

        if(!isset($event['event_id'])){
            return json_encode(['error' => 'Event not found']);
        }

        $mRooms = new Rooms();
        $room = $mRooms->getRoomId($params['roomId']);
        $isOwner = isset($room['room_id']) && $room['creator'] === $params['sender'];

        if($event['sender'] !== $params['sender'] && !$isOwner){
            return json_encode(['error' => 'Only the author or room owner can delete the message']);
        }

        $fileToDelete = null;
        $mEventJson = new EventJson();
        $mEventJson->select()->from()->where("event_id = :event_id AND room_id = :room_id");
        $ej = $mEventJson->fetch([
            'event_id' => $eventId,
            'room_id'  => $params['roomId']
        ]);

        if(isset($ej['event_id'])){
            $ejData = json_decode($ej['json'], true);
            if(isset($ejData['content']['file_url'])){
                $fileToDelete = __DIR__ . '/../../../../../data/uploads/' . basename($ejData['content']['file_url']);
            }
        }

        $redactEventId = $mEvents->addEvent([
            'type'    => 'm.room.redaction',
            'room_id' => $params['roomId'],
            'sender'  => $params['sender']
        ]);

        $displayname = str_replace(['@', ':'.WCO::$domain], ['', ''], $params['sender']);

        $json = json_encode([
            'type'    => 'm.room.redaction',
            'sender'  => $params['sender'],
            'content' => [
                'displayname' => $displayname,
                'redacts'     => $eventId
            ]
        ]);

        $mEventJson->add([
            'event_id' => $redactEventId,
            'room_id'  => $params['roomId'],
            'json'     => $json
        ]);

        $response = json_encode(['status' => 'ok']);
        echo $response;

        if(function_exists('fastcgi_finish_request')){
            fastcgi_finish_request();
        } else {
            header('Content-Length: ' . strlen($response));
            while(ob_get_level()){ ob_end_flush(); }
            flush();
        }

        if($fileToDelete && is_file($fileToDelete)){
            @unlink($fileToDelete);
        }

        return '';
    }

    /**
     * Загрузка аватара комнаты.
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

        $uploadDir = __DIR__ . '/../../../../../data/uploads';
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

    /**
     * Возвращает MD5-хеш файла rooms.js для контроля версий фронтенда.
     * Используется для автообновления страницы при изменении кода.
     * 
     * @return bool
     */
    public function actionVersion() {
        $jsPath = __DIR__ . '/../../../../../web/default/js/rooms.js';
        $hash = file_exists($jsPath) ? md5_file($jsPath) : '';

        header('Content-Type: application/json');
        echo json_encode(['hash' => $hash]);
        return true;
    }

    /**
     * Heartbeat присутствия + список онлайн-пользователей.
     */
    public function actionPresence() {
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            return true;
        }

        $mPresence = new UserPresence();
        $mPresence->heartbeat($mAccesToken->sender);

        $online = $mPresence->getOnlineUsers();
        $userIds = array_map(fn($u) => $u['user_id'], $online);

        header('Content-Type: application/json');
        echo json_encode(['online' => $userIds]);
        return true;
    }

    /**
     * Индикатор набора текста.
     * POST: set typing; DELETE: stop typing.
     */
    public function actionTyping() {
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            return true;
        }

        $mTyping = new TypingIndicator();
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'DELETE') {
            $roomId = $this->data['room_id'] ?? '';
            if ($roomId) {
                $mTyping->stopTyping($mAccesToken->sender, strip_tags($roomId));
            }
            header('Content-Type: application/json');
            echo json_encode(["status" => "ok"]);
            return true;
        }

        $roomId = $this->data['room_id'] ?? '';
        if (!$roomId) {
            http_response_code(400);
            echo json_encode(["error" => "room_id required"]);
            return true;
        }

        $mTyping->setTyping($mAccesToken->sender, strip_tags($roomId));
        $typingUsers = $mTyping->getTypingUsers(strip_tags($roomId), $mAccesToken->sender);

        header('Content-Type: application/json');
        echo json_encode(["status" => "ok", "typing" => $typingUsers]);
        return true;
    }

    /**
     * Получить кто набирает текст в комнате.
     */
    public function actionGetTyping() {
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            return true;
        }

        $roomId = strip_tags($_GET['room_id'] ?? '');
        if (!$roomId) {
            http_response_code(400);
            echo json_encode(["error" => "room_id required"]);
            return true;
        }

        $mTyping = new TypingIndicator();
        $typingUsers = $mTyping->getTypingUsers($roomId, $mAccesToken->sender);

        header('Content-Type: application/json');
        echo json_encode(["typing" => $typingUsers]);
        return true;
    }

    /**
     * Поиск сообщений в комнате.
     */
    public function actionSearch() {
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            return true;
        }

        $roomId = strip_tags($_GET['room_id'] ?? '');
        $query = strip_tags($_GET['q'] ?? '');

        if (!$roomId || !$query) {
            http_response_code(400);
            echo json_encode(["error" => "room_id and q required"]);
            return true;
        }

        $mEventJson = new EventJson();
        $likeParam = '%' . $query . '%';
        $sql = "SELECT event_id, json FROM event_json 
                WHERE room_id = :room_id 
                AND (
                    JSON_UNQUOTE(JSON_EXTRACT(json, '$.content.body')) LIKE :q1 
                    OR JSON_UNQUOTE(JSON_EXTRACT(json, '$.content.file_name')) LIKE :q2
                ) 
                ORDER BY event_id DESC LIMIT 100";
        //self::setAssembly($sql);
        $mEventJson->select()->from()->where("room_id = :room_id 
                AND (
                    JSON_UNQUOTE(JSON_EXTRACT(json, '$.content.body')) LIKE :q1 
                    OR JSON_UNQUOTE(JSON_EXTRACT(json, '$.content.file_name')) LIKE :q2
                ) 
                ORDER BY event_id DESC LIMIT 100");
        $results = $mEventJson->fetchAll([
            'room_id' => $roomId,
            'q1'      => $likeParam,
            'q2'      => $likeParam
        ]);

        $messages = [];
        foreach ($results as $row) {
            $j = json_decode($row['json'], true);
            if ($j) {
                $messages[] = [
                    'event_id' => $row['event_id'],
                    'json'     => $j
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode($messages);
        return true;
    }

    /**
     * Редактирование сообщения.
     */
    public function actionEditMessage() {
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            return true;
        }

        $eventId = $this->modelFilter->string($this->data['event_id'] ?? '');
        $roomId = $this->modelFilter->string($this->data['room_id'] ?? '');
        $newBody = $this->modelFilter->string($this->data['body'] ?? '');

        if (!$eventId || !$roomId || empty($newBody)) {
            http_response_code(400);
            echo json_encode(["error" => "event_id, room_id and body required"]);
            return true;
        }

        $mEvents = new Events();
        $mEvents->select()->from()->where("event_id = :event_id");
        $event = $mEvents->fetch(['event_id' => $eventId]);

        if (!isset($event['event_id'])) {
            http_response_code(404);
            echo json_encode(["error" => "Event not found"]);
            return true;
        }

        if ($event['sender'] !== $mAccesToken->sender) {
            http_response_code(403);
            echo json_encode(["error" => "You can only edit your own messages"]);
            return true;
        }

        $mEventJson = new EventJson();
        $mEventJson->select()->from()->where("event_id = :event_id AND room_id = :room_id");
        $ej = $mEventJson->fetch(['event_id' => $eventId, 'room_id' => $roomId]);

        if (!isset($ej['event_id'])) {
            http_response_code(404);
            echo json_encode(["error" => "Event JSON not found"]);
            return true;
        }

        $jsonData = json_decode($ej['json'], true);
        $jsonData['content']['body'] = strip_tags($newBody);
        $jsonData['content']['edited'] = true;
        $jsonData['content']['edited_at'] = round(microtime(true) * 1000);

        $mEventJson->Update([
            'json'     => json_encode($jsonData),
            'event_id' => $eventId,
            'room_id'  => $roomId
        ], 'event_id = :event_id AND room_id = :room_id');

        header('Content-Type: application/json');
        echo json_encode(["status" => "ok"]);
        return true;
    }

    /**
     * Создание или получение личного диалога (DM) между двумя пользователями.
     */
    public function actionDirectMessage() {
        $mAccesToken = new AccessToken();
        if (!$mAccesToken->getToken()) {
            http_response_code(401);
            echo json_encode(["error" => "\"Invalid token\" error"]);
            return true;
        }

        $targetUserId = strip_tags($this->data['user_id'] ?? '');
        if (!$targetUserId) {
            http_response_code(400);
            echo json_encode(["error" => "user_id required"]);
            return true;
        }

        $senderId = $mAccesToken->sender;

        if ($targetUserId === $senderId) {
            http_response_code(400);
            echo json_encode(["error" => "Cannot create DM with yourself"]);
            return true;
        }

        $mUser = new Users();
        if (!$mUser->checkUser($targetUserId)) {
            http_response_code(404);
            echo json_encode(["error" => "User not found"]);
            return true;
        }

        $mRoomMemberships = new RoomMemberships();
        $mRooms = new Rooms();
        $mEvents = new Events();
        $mEventJson = new EventJson();
        
        $mRoomMemberships->select("t1.room_id")->from()
                ->joinInner(['rm2' => $mRoomMemberships->init()], "rm2.room_id = t1.room_id")
                ->joinInner(['r' => $mRooms->init()], "r.room_id = t1.room_id")
                ->where("t1.user_id = :user1 AND rm2.user_id = :user2 AND r.join_rule = 'invite'");
        $existing = $mRoomMemberships->fetch(['user1' => $senderId, 'user2' => $targetUserId]);

        if (isset($existing['room_id'])) {
            header('Content-Type: application/json');
            echo json_encode(["status" => "ok", "room_id" => $existing['room_id']]);
            return true;
        }

        $uuid = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $roomId = "!$uuid:" . WCO::$domain;

        $mRooms->insert([
            'room_id'   => $roomId,
            'name'      => '',
            'topic'     => '',
            'join_rule' => 'invite',
            'creator'   => $senderId,
            'cdate'     => time()
        ]);

        $eventId1 = $mEvents->addEvent([
            'type'    => 'm.room.member',
            'room_id' => $roomId,
            'sender'  => $senderId
        ]);

        $displayname1 = str_replace(['@', ':' . WCO::$domain], ['', ''], $senderId);
        $mEventJson->add([
            'event_id' => $eventId1,
            'room_id'  => $roomId,
            'json'     => json_encode([
                'type'    => 'm.room.member',
                'sender'  => $senderId,
                'content' => ['displayname' => $displayname1, 'membership' => 'join']
            ])
        ]);

        $mRoomMemberships->addUser([
            'event_id'   => $eventId1,
            'user_id'    => $senderId,
            'sender'     => $senderId,
            'room_id'    => $roomId,
            'membership' => 'join'
        ]);

        $eventId2 = $mEvents->addEvent([
            'type'    => 'm.room.member',
            'room_id' => $roomId,
            'sender'  => $senderId
        ]);

        $displayname2 = str_replace(['@', ':' . WCO::$domain], ['', ''], $targetUserId);
        $mEventJson->add([
            'event_id' => $eventId2,
            'room_id'  => $roomId,
            'json'     => json_encode([
                'type'    => 'm.room.member',
                'sender'  => $senderId,
                'content' => ['displayname' => $displayname2, 'membership' => 'join']
            ])
        ]);

        $mRoomMemberships->addUser([
            'event_id'   => $eventId2,
            'user_id'    => $targetUserId,
            'sender'     => $senderId,
            'room_id'    => $roomId,
            'membership' => 'join'
        ]);

        header('Content-Type: application/json');
        echo json_encode(["status" => "ok", "room_id" => $roomId]);
        return true;
    }
}
