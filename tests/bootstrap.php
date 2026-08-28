<?php
/**
 * Бутстрап для PHPUnit.
 *
 * WCO::LoadConfig()/DB::LoadConfug() вычисляют путь к config/*.php через
 * filter_input(INPUT_SERVER, 'SCRIPT_FILENAME'/'SCRIPT_NAME'), а filter_input() читает снимок
 * $_SERVER, снятый при старте PHP, — переопределение $_SERVER в рантайме на него не влияет.
 * В CLI это указывает на бинарник phpunit, а не на web/index.php, поэтому не вызываем
 * new WCO() напрямую, а подключаем конфиги и раскладываем их по тем же статическим
 * свойствам самостоятельно (WCO::$config, WCO::$domain, wco\db\DB::$config_db).
 */

require __DIR__ . '/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);

$configFile = $projectRoot . '/config/config.php';
if (!file_exists($configFile)) {
    fwrite(STDERR, "Не найден config/config.php — скопируйте его из config/config.exampl.php перед запуском тестов.\n");
    exit(1);
}
include_once $configFile; // объявляет $config и define('SECRET_KEY', ...)
\wco\kernel\WCO::$config = $config;
\wco\kernel\WCO::$domain = 'test.local';

$dbConfigFile = $projectRoot . '/config/db.php';
if (!file_exists($dbConfigFile)) {
    fwrite(STDERR, "Не найден config/db.php — скопируйте его из config/db.exampl.php перед запуском тестов.\n");
    exit(1);
}
include_once $dbConfigFile; // объявляет $config_db
\wco\db\DB::$config_db = $config_db;
