<?php

namespace app\models;

use wco\db\DB;
use wco\kernel\WCO;
use app\models\AccessToken;
use Ramsey\Uuid\Uuid;
use app\models\EventJson;
use app\models\Rooms;

/**
 * Description of Events
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
        $uuid = Uuid::uuid4()->toString();
        $eventId = "$$uuid";
        
        $this->insert([
            'event_id'    => $eventId,
            'type'        => $params['type'],
            'room_id'     => $params['room_id'],
            'sender'      => $params['sender'],
            'received_ts' => time()
        ]);
        
        return $eventId;
    }
    
    public function create($sender) {
        $data = json_decode(file_get_contents("php://input"), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $data = $_POST;
        }
        
        if (!isset($data['room_id'])) {
            http_response_code(401);
            return json_encode(["error" => "Room not found"]);
        }
        
        $room_id = strip_tags($data['room_id']);
        $mRooms = new Rooms();
        $room = $mRooms->getRoomId($room_id);
        
        if (!isset($room['room_id'])) {
            http_response_code(401);
            return json_encode(["error" => "Room not found"]);
        }

        $type = isset($data['msgtype']) ? strip_tags($data['msgtype']) : 'm.text';
        $body = isset($data['body']) ? strip_tags($data['body']) : '';
        $fileUrl = null;
        $fileName = null;
        $fileType = null;
        $fileSize = null;

        $uploadDir = __DIR__ . '/../web/default/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

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

        $chunkCount = isset($data['chunk_count']) ? (int)$data['chunk_count'] : 0;
        $chunkIndex = isset($data['chunk_index']) ? (int)$data['chunk_index'] : 0;
        $uploadId = isset($data['upload_id']) ? strip_tags($data['upload_id']) : null;
        $fileSize = isset($data['file_size']) ? (int)$data['file_size'] : null;

        if ($type === 'm.file' && !empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $fileInfo = $_FILES['file'];
            $tempDir = $uploadDir . '/tmp';
            $chunkDir = $tempDir . '/' . preg_replace('/[^A-Za-z0-9_-]/', '', $uploadId);

            if ($chunkCount > 1 && $chunkIndex > 0 && $uploadId) {
                if (!is_dir($chunkDir)) {
                    mkdir($chunkDir, 0755, true);
                }

                $chunkFile = $chunkDir . '/chunk_' . $chunkIndex;
                if (!move_uploaded_file($fileInfo['tmp_name'], $chunkFile)) {
                    http_response_code(500);
                    return json_encode(["error" => "Upload failed"]);
                }

                if ($chunkIndex < $chunkCount) {
                    return json_encode([
                        'status' => 'chunk_received',
                        'chunk_index' => $chunkIndex,
                        'chunk_count' => $chunkCount
                    ]);
                }

                $fileName = isset($data['file_name']) ? basename(strip_tags($data['file_name'])) : basename($fileInfo['name']);
                $safeName = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $fileName);
                $uniqueName = time() . '_' . bin2hex(random_bytes(8)) . '_' . $safeName;
                $destination = $uploadDir . '/' . $uniqueName;

                $out = fopen($destination, 'wb');
                if (!$out) {
                    http_response_code(500);
                    return json_encode(["error" => "Unable to write file"]);
                }

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

                    while (!feof($in)) {
                        $buffer = fread($in, 1048576);
                        fwrite($out, $buffer);
                    }
                    fclose($in);
                    unlink($partPath);
                }

                fclose($out);
                @rmdir($chunkDir);
                $fileUrl = '/f/' . $uniqueName;
                $fileType = $fileInfo['type'];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if ($fileType === 'application/octet-stream') {
                    if (isset($videoMimes[$fileExt])) {
                        $fileType = $videoMimes[$fileExt];
                    } elseif (isset($audioMimes[$fileExt])) {
                        $fileType = $audioMimes[$fileExt];
                    }
                }
                if ($fileSize === null) {
                    $fileSize = filesize($destination);
                }
                if (empty($body)) {
                    $body = $fileName;
                }

                if ($type === 'm.text') {
                    $type = 'm.file';
                }
            } else {
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
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if ($fileType === 'application/octet-stream') {
                    if (isset($videoMimes[$fileExt])) {
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

        if ($type === 'm.text') {
            if (empty($body)) {
                http_response_code(401);
                return json_encode(["error" => "Body error"]);
            }

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

        $eventId = $this->addEvent([
            'type'    => $type,
            'room_id' => $room['room_id'],
            'sender'  => $sender,
        ]);

        $mUsers = new Users();
        $user = $mUsers->getUserById($sender);

        $content = [
            'body'       => $body,
            'room_id'    => $room['room_id'],
            'sender'     => $sender,
            'avatar_url' => $user['avatar_url'] ?? ''
        ];

        if ($fileUrl) {
            $content['file_url'] = $fileUrl;
            $content['file_name'] = $fileName;
            $content['file_type'] = $fileType;
            $content['file_size'] = $fileSize;
        }

        $json = json_encode([
            'event_id' => $eventId,
            'type'     => $type,
            'room_id'  => $room['room_id'],
            'sender'   => $sender,
            'origin_server_ts' => round(microtime(true) * 1000),
            'content' => $content
        ]);
        
        $mEventJson = new EventJson();  //Сохранение информациио событии
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
        $since = filter_input(INPUT_GET, 'since', FILTER_VALIDATE_INT);

        if ($since === false) {
            $since = null;
        }
        
        $mEventJson = new EventJson();
        $mRoomMemberships = new RoomMemberships();
        
        $sql = $this->select("*")->from();
        $sql->joinInner(['ej' => $mEventJson->init()], "ej.event_id = t1.event_id");
        $sql->joinInner(['m' => $mRoomMemberships->init()], "m.room_id = t1.room_id AND m.user_id = :sender AND m.membership IN ('join','invite')");
        
        $params = ['sender' => $sender];
        if($since !== null){
            $sql->where("t1.received_ts > :since");
            $params['since'] = $since;
        }
        
        $sql->order_by('received_ts ASC')->limit(1000);
        
        $result = $this->fetchAll($params);
        
        $arr = [];
        $arr['next_batch'] = 0;
        
        foreach ($result as $key => $event){
            if($event['membership'] === 'invite'){                          // Если это приглашение, то добавляем в массив invite
                $arr['rooms']['invite'][$event['room_id']] = [
                    'invite_state' => [
                        'events' => []
                    ],
                ];
            }

            if($event['membership'] === 'join'){                            // Если это присоединение, то добавляем в массив join
                $arr['rooms']['join'][$event['room_id']]['events'][] = [
                    'event_id' => $event['event_id'],
                    'json' => json_decode($event['json'], true)
                ];
            }

            if($arr['next_batch'] < $event['received_ts']){                 // Если текущий next_batch меньше, чем received_ts события, то обновляем next_batch
                $arr['next_batch'] = $event['received_ts'];
            }
        }
        
        return json_encode($arr);
    }
    
    /**
     * @param string $roomId
     * @param string $userId
     * @param string $sender
     * @param string $membership По умолчанию invite
     * @return bool
     */
    public function invite(string $roomId, string $userId, string $sender, string $membership = 'invite'): bool {
        $mRoomMemberships = new RoomMemberships();
        $member = $mRoomMemberships->getRoomMember($roomId, $userId);
        
        $type = 'm.room.member';

        $eventId = $this->addEvent([
            'type'    => $type,
            'room_id' => $roomId,
            'sender'  => $sender,
        ]);

        $mEventJson = new EventJson();
        
        $displayname = str_replace(['@', ':'.WCO::$domain], ['', ''], $userId);

        $json = json_encode([
            'type'   => $type,
            'sender' => $sender,
            'content' => [
                'displayname' => $displayname,
                'membership'  => $membership
            ]
        ]);

        $mEventJson->add([
            'event_id' => $eventId,
            'room_id'  => $roomId,
            'json'     => $json
        ]);

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
