<?php
use app\models\Users;

class SiteController extends \wco\kernel\Controller{
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
}
