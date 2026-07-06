<?php
require __DIR__ . '/../vendor/autoload.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/(?:f|default/uploads)/([^/]+)$#', $uri, $m)) {
    $_GET['file'] = basename(urldecode($m[1]));
    $kernel = new wco\kernel\WCO();
    require __DIR__ . '/../domain/default/controllers/SiteController.php';
    $controller = new SiteController();
    $controller->actionDownload();
    exit;
}

$kernel = new wco\kernel\WCO();
$kernel->RunKernel();
