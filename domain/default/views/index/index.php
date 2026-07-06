<?php
use wco\forms\Form;
use wco\kernel\WCO;

$this->title = 'Авторизация';
$aut = new Form();
?>

<div class="container">
    <div class="row d-flex justify-content-center align-items-center">
        <div class="col-sm-11 col-lg-5">
            <div class="block-authorisation border-primary mb-3" style="max-width: 540px;">
                <div class="row g-0">
                    <div class="col-sm-1 col-md-5">
                      <img src="/default/images/logotip.png" class="card-img-top" alt="NeChat">
                    </div>
                    
                    <div class="col-sm-11 col-md-7">
                        <div class="card-body">
                            <h1><?=$this->title?></h1>

                            <div id="notify"></div>

                            <div>
                                <?=$aut->FormStart('authorization', 'POST', '')?>
                                <div class="mb-3">
                                    <label for="login" class="form-label">Логин</label>
                                    <div><?=$aut->Input('text', 'login')->Field()?></div>
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Пароль</label>
                                    <div><?=$aut->Input('password', 'password')->Field()?></div>
                                </div>


                                <div class="d-grid gap-2">
                                    <?=$aut->Input(Form::INPUT_SUBMIT, 'aut', 'Авторизация', ['class' => 'btn btn-primary aut-button'])->Field()?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">Home</li>
                        <li class="breadcrumb-item active" aria-current="page"><a href="<?=WCO::Url('/reg')?>">Регистрация</a></li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        if (localStorage.getItem('token') !== null && localStorage.getItem('token') !== '') {
            window.location.href = '/chat';
        }
    });
    
    const form = document.getElementById('authorization');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const data = Object.fromEntries(new FormData(form).entries());

        const res = await fetch('/api/v1/authorization/', {
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

        localStorage.setItem('token', result.token);
        document.cookie = 'token=' + encodeURIComponent(result.token) + '; path=/; max-age=86400; SameSite=Lax';
        if (result.user_id) {
            localStorage.setItem('user_id', result.user_id);
        }
        window.location.href = '/chat';
        form.reset();
        return;
    });
</script>