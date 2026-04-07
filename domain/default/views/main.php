<?php
use wco\kernel\WCO;
?>

<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset = utf-8">
        <?php
        $this->Seo();
        ?>
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link rel="icon" href="<?=WCO::$config['protocol'].$_SERVER['SERVER_NAME']?>/default/favicon.ico" type="image/x-icon">
    </head>
    
    <body>
        <?=$this->views?>
        <?=WCO::getBodyEnd()?>
    </body>
</html>

