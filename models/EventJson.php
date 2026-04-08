<?php

namespace app\models;

use wco\db\DB;
use wco\kernel\WCO;
use app\models\AccessToken;

/**
 * Description of EventJson
 *
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class EventJson extends DB{
    function __construct() {
        parent::__construct();
    }

    public function init() {
        return 'event_json';
    }
    
    public function add($col) {
        $this->insert($col);
    }
}
