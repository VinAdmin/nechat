<?php
use wco\forms\Form;

$this->title = 'Страницы Index';
$aut = new Form();
?>

<h1>Страницы Index</h1>

<div>
    <?=$aut->FormStart('authorization', 'POST', '')?>
    <div><?=$aut->Input('text', 'login')->Field()?></div>
    <div><?=$aut->Input('text', 'password')->Field()?></div>
    <div><?=$aut->Input(Form::INPUT_SUBMIT, 'aut', 'Авторизация')->Field()?></div>
</div>