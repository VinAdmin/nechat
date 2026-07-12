<?php

namespace app\models;

use wco\db\DB;
use wco\kernel\WCO;
use app\models\AccessToken;
use Ramsey\Uuid\Uuid;
use app\models\EventJson;
use app\models\Rooms;

/**
 * Модель событий (events).
 *
 * Управляет созданием, получением и синхронизацией событий в комнатах.
 * События представляют собой ключевые действия в системе: создание комнат,
 * отправку сообщений (текст и файлы), приглашение пользователей и т.д.
 *
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class Events extends DB{
    function __construct() {
        parent::__construct();
    }

    public function init() {
        return 'events';
    }
    
    /**
     * Добавляет событие.
     * 
     * @param array $params [type, room_id, sender]
     * @return string
     */
    public function addEvent($params): string {
        // Генерируем уникальный ID события по формату Matrix ($$uuid)
        $uuid = Uuid::uuid4()->toString();
        $eventId = "$$uuid";
        
        $this->insert([
            'event_id'    => $eventId,
            'type'        => $params['type'],
            'room_id'     => $params['room_id'],
            'sender'      => $params['sender'],
            'received_ts' => time()  // Время получения события (Unix-timestamp)
        ]);
        
        return $eventId;
    }
    
    /**
     * Создаёт сообщение (текст или файл) в комнате.
     *
     * Поддерживает отправку текстовых сообщений (m.text) и файлов (m.file).
     * Файлы загружаются посредством chunk-загрузки для больших файлов.
     * Проверяет права отправки: забаненные и приглашённые пользователи не могут писать.
     *
     * @param string $sender user_id отправителя
     * @return string JSON с event_id или сообщение об ошибке
     */
    public function create($sender) {
        // Парсим тело запроса: сначала пытаемся JSON, затем fallback на $_POST
        $data = json_decode(file_get_contents("php://input"), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $data = $_POST;
        }
        
        if (!isset($data['room_id'])) {
            http_response_code(401);
            return json_encode(["error" => "Room not found"]);
        }
        
        // Получаем комнату и проверяем её существование
        $room_id = strip_tags($data['room_id']);
        $mRooms = new Rooms();
        $room = $mRooms->getRoomId($room_id);
        
        if (!isset($room['room_id'])) {
            http_response_code(401);
            return json_encode(["error" => "Room not found"]);
        }

        // Тип сообщения: m.text (текст) или m.file (файл)
        $type = isset($data['msgtype']) ? strip_tags($data['msgtype']) : 'm.text';
        $body = isset($data['body']) ? strip_tags($data['body']) : '';
        $replyTo = isset($data['reply_to']) ? strip_tags($data['reply_to']) : '';
        $fileUrl = null;
        $fileName = null;
        $fileType = null;
        $fileSize = null;

        // Директория для загрузки файлов
        $uploadDir = __DIR__ . '/../data/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Таблицы соответствия расширений MIME-типам для определения типа загружаемого файла
        $videoExts = ['mp4', 'webm', 'ogg', 'mkv', 'avi', 'mov', 'flv', 'wmv', '3gp'];
        $videoMimes = [
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg',
            'mkv' => 'video/x-matroska', 'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime', 'flv' => 'video/x-flv',
            'wmv' => 'video/x-ms-wmv', '3gp' => 'video/3gpp'
        ];
        $audioExts = ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma', 'opus', 'webm'];
        $audioMimes = [
            'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg',
            'flac' => 'audio/flac', 'aac' => 'audio/aac', 'm4a' => 'audio/mp4',
            'wma' => 'audio/x-ms-wma', 'opus' => 'audio/opus', 'webm' => 'audio/webm'
        ];
        $imageMimes = [
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
            'avif' => 'image/avif'
        ];

        // Параметры chunk-загрузки (для больших файлов разбивается на части)
        $chunkCount = isset($data['chunk_count']) ? (int)$data['chunk_count'] : 0;
        $chunkIndex = isset($data['chunk_index']) ? (int)$data['chunk_index'] : 0;
        $uploadId = isset($data['upload_id']) ? strip_tags($data['upload_id']) : null;
        $fileSize = isset($data['file_size']) ? (int)$data['file_size'] : null;

        // Обработка загрузки файла
        if ($type === 'm.file' && !empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $fileInfo = $_FILES['file'];
            $tempDir = $uploadDir . '/tmp';
            // Безопасное имя директории для чанков (только буквы, цифры, дефисы, подчёркивания)
            $chunkDir = $tempDir . '/' . preg_replace('/[^A-Za-z0-9_-]/', '', $uploadId);

            // Многочастичная загрузка: файл разбит на несколько чанков
            if ($chunkCount > 1 && $chunkIndex > 0 && $uploadId) {
                if (!is_dir($chunkDir)) {
                    mkdir($chunkDir, 0755, true);
                }

                // Сохраняем текущий чанк во временную директорию
                $chunkFile = $chunkDir . '/chunk_' . $chunkIndex;
                if (!move_uploaded_file($fileInfo['tmp_name'], $chunkFile)) {
                    http_response_code(500);
                    return json_encode(["error" => "Upload failed"]);
                }

                // Если ещё не все чанки получены — возвращаем статус ожидания
                if ($chunkIndex < $chunkCount) {
                    return json_encode([
                        'status' => 'chunk_received',
                        'chunk_index' => $chunkIndex,
                        'chunk_count' => $chunkCount
                    ]);
                }

                // Все чанки получены — собираем файл из частей
                $fileName = isset($data['file_name']) ? basename(strip_tags($data['file_name'])) : basename($fileInfo['name']);
                $safeName = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $fileName);
                $uniqueName = time() . '_' . bin2hex(random_bytes(8)) . '_' . $safeName;
                $destination = $uploadDir . '/' . $uniqueName;

                $out = fopen($destination, 'wb');
                if (!$out) {
                    http_response_code(500);
                    return json_encode(["error" => "Unable to write file"]);
                }

                // Склеиваем все чанки в один файл последовательно
                for ($i = 1; $i <= $chunkCount; $i++) {
                    $partPath = $chunkDir . '/chunk_' . $i;
                    if (!is_file($partPath)) {
                        fclose($out);
                        http_response_code(500);
                        return json_encode(["error" => "Missing chunk {$i}"]);
                    }

                    $in = fopen($partPath, 'rb');
                    if (!$in) {
                        fclose($out);
                        http_response_code(500);
                        return json_encode(["error" => "Unable to read chunk {$i}"]);
                    }

                    // Читаем по 1 МБ для эффективного копирования
                    while (!feof($in)) {
                        $buffer = fread($in, 1048576);
                        fwrite($out, $buffer);
                    }
                    fclose($in);
                    unlink($partPath);  // Удаляем временный чанк после записи
                }

                fclose($out);
                @rmdir($chunkDir);  // Удаляем временную директорию чанков
                $fileUrl = '/f/' . $uniqueName;
                $fileType = $fileInfo['type'];

                // Если сервер не определил MIME-тип — определяем по расширению
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if ($fileType === 'application/octet-stream') {
                    if (isset($imageMimes[$fileExt])) {
                        $fileType = $imageMimes[$fileExt];
                    } elseif (isset($videoMimes[$fileExt])) {
                        $fileType = $videoMimes[$fileExt];
                    } elseif (isset($audioMimes[$fileExt])) {
                        $fileType = $audioMimes[$fileExt];
                    }
                }
                if ($fileSize === null) {
                    $fileSize = filesize($destination);
                }
                if (empty($body)) {
                    $body = $fileName;  // Имя файла как текст сообщения по умолчанию
                }

                // Если тип был m.text, меняем на m.file (файл прикреплён)
                if ($type === 'm.text') {
                    $type = 'm.file';
                }
            } else {
                // Обычная (одноразовая) загрузка файла без чанков
                $fileName = basename($fileInfo['name']);
                $safeName = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $fileName);
                $uniqueName = time() . '_' . bin2hex(random_bytes(8)) . '_' . $safeName;
                $destination = $uploadDir . '/' . $uniqueName;

                if (!move_uploaded_file($fileInfo['tmp_name'], $destination)) {
                    http_response_code(500);
                    return json_encode(["error" => "Upload failed"]);
                }

                $fileUrl = '/f/' . $uniqueName;
                $fileType = $fileInfo['type'];

                // Определяем MIME-тип по расширению, если сервер вернул application/octet-stream
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if ($fileType === 'application/octet-stream') {
                    if (isset($imageMimes[$fileExt])) {
                        $fileType = $imageMimes[$fileExt];
                    } elseif (isset($videoMimes[$fileExt])) {
                        $fileType = $videoMimes[$fileExt];
                    } elseif (isset($audioMimes[$fileExt])) {
                        $fileType = $audioMimes[$fileExt];
                    }
                }
                if ($fileSize === null) {
                    $fileSize = (int)$fileInfo['size'];
                }

                if (empty($body)) {
                    $body = $fileName;
                }

                if ($type === 'm.text') {
                    $type = 'm.file';
                }
            }
        }

        // Проверка прав на отправку сообщений
        if ($type === 'm.text') {
            if (empty($body)) {
                http_response_code(401);
                return json_encode(["error" => "Body error"]);
            }

            // Запрещаем отправку забаненным и приглашённым (не вступившим) пользователям
            $modelRoomMemberships = new RoomMemberships();
            $modelRoomMemberships->select()->from()
                    ->where("room_id = :room_id AND user_id = :sender AND membership IN ('ban', 'invite')");
            $membership_res = $modelRoomMemberships->fetch([
                'sender' => $sender,
                'room_id' => $room['room_id'],
            ]);
            if ($membership_res) {
                http_response_code(401);
                return json_encode(["error" => "Sending a message is prohibited"]);
            }
        } elseif ($type === 'm.file') {
            if (!$fileUrl) {
                http_response_code(401);
                return json_encode(["error" => "File upload error"]);
            }

            // Та же проверка прав для файловых сообщений
            $modelRoomMemberships = new RoomMemberships();
            $modelRoomMemberships->select()->from()
                    ->where("room_id = :room_id AND user_id = :sender AND membership IN ('ban', 'invite')");
            $membership_res = $modelRoomMemberships->fetch([
                'sender' => $sender,
                'room_id' => $room['room_id'],
            ]);
            if ($membership_res) {
                http_response_code(401);
                return json_encode(["error" => "Sending a message is prohibited"]);
            }
        }

        // Создаём запись события в БД
        $eventId = $this->addEvent([
            'type'    => $type,
            'room_id' => $room['room_id'],
            'sender'  => $sender,
        ]);

        // Получаем данные отправителя (аватар и т.д.)
        $mUsers = new Users();
        $user = $mUsers->getUserById($sender);

        $content = [
            'body'       => $body,
            'room_id'    => $room['room_id'],
            'sender'     => $sender,
            'avatar_url' => $user['avatar_url'] ?? ''
        ];

        // Если это ответ на другое сообщение — добавляем информацию о оригинале
        if ($replyTo) {
            $replyToData = ['event_id' => $replyTo];
            $sql = "SELECT json FROM event_json WHERE event_id = :eid AND room_id = :rid LIMIT 1";
            self::setAssembly($sql);
            $mReplied = $this->fetch(['eid' => $replyTo, 'rid' => $room['room_id']]);
            if ($mReplied) {
                $repliedJson = json_decode($mReplied['json'], true);
                if ($repliedJson) {
                    $replyToData['sender'] = $repliedJson['sender'] ?? '';
                    $replyToData['body'] = $repliedJson['content']['body'] ?? $repliedJson['content']['file_name'] ?? '';
                }
            }
            $content['reply_to'] = $replyToData;
        }

        // Добавляем метаданные файла в контент, если это файловое сообщение
        if ($fileUrl) {
            $content['file_url'] = $fileUrl;
            $content['file_name'] = $fileName;
            $content['file_type'] = $fileType;
            $content['file_size'] = $fileSize;
        }

        // Собираем JSON события по формату Matrix
        $json = json_encode([
            'event_id' => $eventId,
            'type'     => $type,
            'room_id'  => $room['room_id'],
            'sender'   => $sender,
            'origin_server_ts' => round(microtime(true) * 1000),  // Временная метка в миллисекундах
            'content' => $content
        ]);
        
        // Сохраняем JSON представление события
        $mEventJson = new EventJson();
        $mEventJson->add([
            'event_id' => $eventId,
            'room_id'  => $room['room_id'],
            'json'     => $json
        ]);
        
        return json_encode([
            'status'  => 'ok',
            'event_id' => $eventId
        ]);
    }
    
    /**
     * Возвращает список событий для заданного пользователя.
     *
     * @param string $sender
     * @return string Список событий
     */
    public function sync(string $sender): string {
        // Параметр since — временная метка для инкрементальной синхронизации
        $since = filter_input(INPUT_GET, 'since', FILTER_VALIDATE_INT);

        if ($since === false) {
            $since = null;
        }
        
        $mEventJson = new EventJson();
        $mRoomMemberships = new RoomMemberships();
        
        // Получаем события из комнат, где пользователь является участником (join/invite)
        $sql = $this->select("t1.event_id, t1.type, t1.room_id, t1.sender, t1.received_ts, ej.json, m.membership")->from();
        $sql->joinInner(['ej' => $mEventJson->init()], "ej.event_id = t1.event_id");
        $sql->joinInner(['m' => $mRoomMemberships->init()], "m.room_id = t1.room_id AND m.user_id = :sender AND m.membership IN ('join','invite')");
        
        $params = ['sender' => $sender];
        // Если указан since — возвращаем только события новее этой метки
        if($since !== null){
            $sql->where("t1.received_ts > :since");
            $params['since'] = $since;
        }
        
        $sql->order_by('received_ts ASC')->limit(1000);
        
        $result = $this->fetchAll($params);
        
        $arr = [];
        $arr['next_batch'] = 0;
        
        // Группируем события по типу членства: invite и join попадают в разные массивы
        foreach ($result as $key => $event){
            if($event['membership'] === 'invite'){
                $arr['rooms']['invite'][$event['room_id']] = [
                    'invite_state' => [
                        'events' => []
                    ],
                ];
            }

            if($event['membership'] === 'join'){
                $arr['rooms']['join'][$event['room_id']]['events'][] = [
                    'event_id'   => $event['event_id'],
                    'type'       => $event['type'],
                    'received_ts' => $event['received_ts'],
                    'json'       => json_decode($event['json'], true)
                ];
            }

            // Обновляем next_batch — максимальная временная метка для следующей синхронизации
            if($arr['next_batch'] < $event['received_ts']){
                $arr['next_batch'] = $event['received_ts'];
            }
        }
        
        return json_encode($arr);
    }
    
    /**
     * Приглашает пользователя в комнату (или изменяет его статус членства).
     *
     * Создаёт событие m.room.member и запись членства в room_memberships.
     * Если пользователь уже состоит в комнате (не забанен) — возвращает false.
     *
     * @param string $roomId    ID комнаты
     * @param string $userId    ID приглашаемого пользователя
     * @param string $sender    ID отправителя приглашения
     * @param string $membership Тип членства (по умолчанию 'invite')
     * @return bool true при успехе, false если пользователь уже в комнате
     */
    public function invite(string $roomId, string $userId, string $sender, string $membership = 'invite'): bool {
        $mRoomMemberships = new RoomMemberships();
        $member = $mRoomMemberships->getRoomMember($roomId, $userId);
        
        // Если пользователь уже состоит в комнате (и не забанен) — отклоняем приглашение
        if(isset($member['user_id']) && $member['membership'] !== 'ban'){
            http_response_code(400);
            echo json_encode(["error" => "User is already in this room"]);
            return false;
        }

        $type = 'm.room.member';

        // Создаём событие m.room.member (приглашение/бан/вступление)
        $eventId = $this->addEvent([
            'type'    => $type,
            'room_id' => $roomId,
            'sender'  => $sender,
        ]);

        $mEventJson = new EventJson();
        
        // Извлекаем отображаемое имя из user_id (убираем @ и :domain)
        $displayname = str_replace(['@', ':'.WCO::$domain], ['', ''], $userId);

        $json = json_encode([
            'type'   => $type,
            'sender' => $sender,
            'content' => [
                'displayname' => $displayname,
                'membership'  => $membership
            ]
        ]);

        // Сохраняем JSON представление события
        $mEventJson->add([
            'event_id' => $eventId,
            'room_id'  => $roomId,
            'json'     => $json
        ]);

        // Создаём/обновляем запись о членстве пользователя в комнате
        $mRoomMemberships->addUser([
            'event_id'   => $eventId,
            'user_id'    => $userId,
            'sender'     => $sender,
            'room_id'    => $roomId,
            'membership' => $membership
        ]);

        return true;
    }
}
