<?php
use wco\forms\Form;
use wco\kernel\WCO;

$this->title = 'Регистрация в проекте';
$this->description = 'Регистрация в проекте в интерактивном сервисе';
$form = new Form();
?>

<div class="container">
    <div class="row justify-content-md-center align-items-center">
        <div class="col-sm-11 col-lg-5">
            <div id="notify"></div>
            
            <nav style="--bs-breadcrumb-divider: '>';" aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= WCO::Url('/')?>">Авторизация</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?=$this->title?></li>
                </ol>
            </nav>
            
            <h1><?=$this->title?></h1>
            
            <?=$form->FormStart('reg','post',null,'off')?>
                <div class="form-group">
                    <label for="login">Логин *</label>
                    <?=$form->Input('text','login', '', [
                        'class'=>'form-control col-lg-12',
                        'atr'=>'required',
                        'placeholder'=>'Логин'
                    ])->Field()?>
                </div>
                <div class="form-group">
                    <label>Пароль *</label>
                    <?=$form->Input('password','password',null,[
                        'class'=>'form-control col-lg-12',
                        'atr'=>'required',
                    ])->Field()?>
                </div>
                <div class="form-group">
                    <label>Еще раз пароль *</label>
                    <?=$form->Input('password','re_password',null,[
                        'class'=>'form-control col-lg-12',
                        'atr'=>'required',
                    ])->Field()?>
                </div>
                    <?=$form->Input('submit','registr','Зарегистрироваться',[
                        'class'=>'btn btn-primary',
                    ])->Field()?>
            <?=$form->FormEnd()?>
        </div>
    </div>
</div>

<script>
const form = document.getElementById('reg');

form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const data = Object.fromEntries(new FormData(form).entries());

    const res = await fetch('/api/v1/registration/', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(data)
    });

    const result = await res.json();

    if(result.error){
        notify(result.error, 'warning', 3000 * 5);
        return;
    }
    
    window.location.href = '/';
    form.reset();
    return;
});
</script>
