<?php
use wco\forms\Form;

$this->title = 'Страницы Index';
$aut = new Form();
?>

<h1>Страницы Index</h1>

<div id="notify"></div>

<div>
    <?=$aut->FormStart('authorization', 'POST', '')?>
    <div><?=$aut->Input('text', 'login')->Field()?></div>
    <div><?=$aut->Input('text', 'password')->Field()?></div>
    <div><?=$aut->Input(Form::INPUT_SUBMIT, 'aut', 'Авторизация')->Field()?></div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        if (localStorage.getItem('token') !== null) {
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
        window.location.href = '/chat';
        form.reset();
        return;
    });
</script>