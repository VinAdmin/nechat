<?php

namespace app\models;

use wco\db\DB;
use wco\kernel\WCO;
use app\models\AccessToken;
use app\models\RoomMemberships;
use app\models\UserPresence;
use app\models\TypingIndicator;

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
    
    public function getUserById(string $user_id): array {
        $this->select()->from()->where("user_id = :user_id");
        $result = $this->fetch(['user_id' => $user_id]);
        return $result ?: [];
    }

    public function changePassword(string $user_id, string $oldPassword, string $newPassword): string {
        $user = $this->getUserById($user_id);
        if (!$user) {
            return json_encode(["error" => "User not found"]);
        }

        if (!password_verify($oldPassword, $user['password'])) {
            return json_encode(["error" => "Old password is incorrect"]);
        }

        $this->Update([
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
            'user_id'  => $user_id
        ], 'user_id = :user_id');

        return json_encode(["status" => "ok"]);
    }

    /**
     * Отображаемое имя пользователя: заполненное поле `name`, иначе сам user_id
     * (fallback вида `@login:domain`). Используется везде, где в интерфейсе
     * показывается отправитель/участник.
     *
     * @param string $user_id
     * @return string
     */
    public function displayName(string $user_id): string {
        $user = $this->getUserById($user_id);
        $name = trim((string) ($user['name'] ?? ''));

        return $name !== '' ? $name : $user_id;
    }

    public function updateProfile(string $user_id, array $data): string {
        $update = [];

        if(isset($data['password']) && !empty($data['password'])){
            $update['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if(isset($data['avatar_url'])){
            $update['avatar_url'] = strip_tags($data['avatar_url']);
        }

        // Смена отображаемого имени: запоминаем старое значение до апдейта, чтобы
        // потом разослать системное событие «X сменил имя на Y» по комнатам.
        // Пустая строка допустима — это сброс имени (возврат к user_id).
        $nameChanged = false;
        $oldName = '';
        $newName = '';
        if(isset($data['name'])){
            $oldName = trim((string) ($this->getUserById($user_id)['name'] ?? ''));
            $newName = mb_substr(trim(strip_tags($data['name'])), 0, 255);
            $update['name'] = $newName;
            $nameChanged = ($newName !== $oldName); // no-op не рассылаем
        }

        if(empty($update)){
            return json_encode(["status" => "ok", "message" => "Nothing to update"]);
        }

        $update['user_id'] = $user_id;
        $this->Update($update, 'user_id = :user_id');

        if($nameChanged){
            // В событии показываем отображаемые имена (при пустом name — user_id),
            // флаг cleared => фронтенд напишет «вернул логин» вместо «сменил имя на».
            $prevDisplayName = $oldName !== '' ? $oldName : $user_id;
            $newDisplayName  = $newName !== '' ? $newName : $user_id;
            (new Events())->emitDisplayNameChange($user_id, $prevDisplayName, $newDisplayName, $newName === '');
        }

        return json_encode(["status" => "ok"]);
    }

    /**
     * Полное удаление профиля пользователя.
     *
     * Удаляет только собственные данные пользователя: все токены доступа
     * (завершает все сессии), членства в комнатах, записи присутствия и
     * индикаторов набора, и саму строку в `users`.
     *
     * Комнаты, созданные пользователем (`rooms.creator`), а также его события
     * (`events` / `event_json`) намеренно НЕ удаляются — такие комнаты
     * остаются без владельца, история сообщений сохраняется.
     *
     * Файл аватара из `data/uploads/` удаляется отдельно в
     * `V1Controller::actionDeleteAccount()` (работа с ФС — на уровне контроллера).
     *
     * @param string $user_id
     * @return void
     */
    public function deleteAccount(string $user_id): void {
        (new AccessToken())->delete("user_id = :user_id")
                ->execute([':user_id' => $user_id]);

        (new RoomMemberships())->delete("user_id = :user_id")
                ->execute([':user_id' => $user_id]);

        (new UserPresence())->delete("user_id = :user_id")
                ->execute([':user_id' => $user_id]);

        (new TypingIndicator())->delete("user_id = :user_id")
                ->execute([':user_id' => $user_id]);

        $this->delete("user_id = :user_id")
                ->execute([':user_id' => $user_id]);
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
