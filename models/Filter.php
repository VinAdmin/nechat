<?php
namespace app\models;

/**
 * Description of Filter
 *
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class Filter {
    static function string(string $param): string {
        $param = htmlspecialchars($param, ENT_QUOTES, 'UTF-8');
        $param = strip_tags($param);
        $disallow = ['~', '\'', '"', '<', '>', '%'];
        $param = str_replace($disallow, '', $param);
        
        return $param;
    }
}
