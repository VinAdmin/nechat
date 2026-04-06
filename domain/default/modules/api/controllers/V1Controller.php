<?php
use wco\kernel\WCO;
use app\models\Users;

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
}
