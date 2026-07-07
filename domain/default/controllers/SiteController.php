<?php
use app\models\Users;
use app\models\AccessToken;

class SiteController extends \wco\kernel\Controller{
    public $mimeMap = [
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'ogg' => 'video/ogg',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'flac' => 'audio/flac',
        'aac' => 'audio/aac', 'opus' => 'audio/opus',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp',
        'pdf' => 'application/pdf',
    ];
    
    public function actionIndex() {
        $this->generate('/index/index.php');
        return true;
    }
    
    public function actionReg() {
        $this->generate('/index/reg.php');
        return true;
    }
    
    public function actionChat() {
        $this->generate('/index/chat.php');
        return true;
    }
    
    public function actionDownload() {
        $mAccesToken = new AccessToken();

        $token = $_COOKIE['token'] ?? '';
        if ($token) {
            $authOk = $mAccesToken->checkToken($token);
        } else {
            $authOk = $mAccesToken->getToken();
        }

        if (!$authOk) {
            http_response_code(401);
            return true;
        }

        $fileName = isset($_GET['file']) ? basename($_GET['file']) : '';
        if (!$fileName) {
            http_response_code(400);
            return true;
        }
        
        $uploadDir = dirname(__DIR__, 3) . '/data/uploads';
        $filePath = $uploadDir . '/' . $fileName;
        

        if (!is_file($filePath)) {
            http_response_code(404);
            return true;
        }

        $mimeType = mime_content_type($filePath);
        if (!$mimeType) {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            $mimeType = $this->mimeMap[$ext] ?? 'application/octet-stream';
        }

        $fileSize = filesize($filePath);

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $fileSize);
        header('Accept-Ranges: bytes');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="' . $fileName . '"');

        if (isset($_SERVER['HTTP_RANGE'])) {
            if (preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches)) {
                $start = (int)$matches[1];
                $end = isset($matches[2]) ? (int)$matches[2] : $fileSize - 1;

                if ($start >= $fileSize || $end >= $fileSize) {
                    http_response_code(416);
                    header('Content-Range: bytes */' . $fileSize);
                    return true;
                }

                http_response_code(206);
                header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
                header('Content-Length: ' . ($end - $start + 1));

                $fp = fopen($filePath, 'rb');
                fseek($fp, $start);
                $remaining = $end - $start + 1;
                while ($remaining > 0 && !feof($fp)) {
                    $readSize = min(8192, $remaining);
                    echo fread($fp, $readSize);
                    $remaining -= $readSize;
                    flush();
                }
                fclose($fp);
            } else {
                readfile($filePath);
            }
        } else {
            readfile($filePath);
        }

        flush();
        exit;
    }
}
