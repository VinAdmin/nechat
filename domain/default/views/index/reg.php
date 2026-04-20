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
            <div class="block-authorisation border-primary mb-3">
                <div class="row g-0">
                    <div class="col-sm-1 col-md-5">
                        <img src="/default/images/logotip.png" class="card-img-top" alt="NeChat">
                    </div>
                    
                    <div class="col-sm-11 col-md-7">
                        <div class="card-body">
                            <h1><?=$this->title?></h1>
                            
                            <div id="notify"></div>
            
                            <?=$form->FormStart('reg','post',null,'off')?>
                                <div class="mb-3">
                                    <label for="login">Логин *</label>
                                    <?=$form->Input('text','login', '', [
                                        'class'=>'form-control col-lg-12',
                                        'atr'=>'required',
                                        'placeholder'=>'Логин'
                                    ])->Field()?>
                                </div>
                                <div class="mb-3">
                                    <label>Пароль *</label>
                                    <?=$form->Input('password','password',null,[
                                        'class'=>'form-control col-lg-12',
                                        'atr'=>'required',
                                    ])->Field()?>
                                </div>
                                <div class="mb-3">
                                    <label>Еще раз пароль *</label>
                                    <?=$form->Input('password','re_password',null,[
                                        'class'=>'form-control col-lg-12',
                                        'atr'=>'required',
                                    ])->Field()?>
                                </div>
                                <div class="d-grid gap-2">
                                    <?=$form->Input('submit','registr','Зарегистрироваться',[
                                        'class'=>'btn btn-primary',
                                    ])->Field()?>
                                </div>
                            <?=$form->FormEnd()?>
                        </div>
                    </div>
                </div>
                
                <nav style="--bs-breadcrumb-divider: '>';" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?= WCO::Url('/')?>">Авторизация</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?=$this->title?></li>
                    </ol>
                </nav>
            </div>
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
