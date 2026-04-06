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
        <div class="container">
            <a href="<?=WCO::Url('/')?>">
                <img src="/default/images/logotip.png" alt="alt" class="img-fluid"/>
            </a>
        </div>
        
        <div class="container-fluid">
            <div class="row">
                <div class="col-xl-3 left_block">
                    <div class="card">
                        <div class="card-body">
                            <ul class="nav flex-column">
                                <li class="nav-item">
                                    <a class="nav-link active" href="<?=WCO::Url('/')?>">Главная</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link active" href="<?=WCO::Url('/reg')?>">Регистрация</a>
                                </li>
                                <li class="nav-item">
                                  <a class="nav-link" href="#">Ссылка</a>
                                </li>
                                <li class="nav-item">
                                  <a class="nav-link disabled" href="#" tabindex="-1" aria-disabled="true">Отключенная</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-9 content">
                    <?=$this->views?>
                </div>
            </div>
        </div>
        <?=WCO::getBodyEnd()?>
    </body>
</html>

