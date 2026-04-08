<?php
use wco\forms\Form;

$this->title = 'Чат';
$fCreateRoom = new Form();
$fMessages = new Form();
?>
<div id="notify"></div>

<div class="prog_chat">
    <div class="rooms_board">
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRoom">
                Добавить
            </button>
        </div>
        
        <div class="rooms" id="rooms">
            
        </div>
    </div>
    
    <div class="chat">
        <div id="messages" class="messages">
            
        </div>
        <?=$fMessages->FormStart('sendMessage')?>
        <div class="messageComposer">
            <?=$fMessages->Input('text', 'body', '', ['class' => 'msgInput'])->Field()?>
            <?=$fMessages->Input(Form::INPUT_SUBMIT, 'send', 'Отправить', [
                    'class' => 'btn btn-primary'
                ])->Field()?>
        </div>
        <?=$fMessages->FormEnd();?>
    </div>
</div>

<!-- Создание комнаты -->
<div class="modal fade" id="createRoom" tabindex="-1" aria-labelledby="createRoomLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Создание комнаты</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <?=$fCreateRoom->FormStart('formCreateRoom', 'POST')?>
            <div class="modal-body">
                <div>
                    <label>Название комнаты</label>
                    <?=$fCreateRoom->Input('text', 'name')->Field()?>
                </div>
            </div>
            <div class="modal-footer">
                    <?=$fCreateRoom->Input(Form::INPUT_SUBMIT, 'create', 'Создать', [
                        'class' => 'btn btn-primary'
                    ])->Field()?>
            </div>
            <?=$fCreateRoom->FormEnd()?>
        </div>
    </div>
</div>

<script>
    async function joinedRooms(token) {
        const res = await fetch('/api/v1/joined_rooms/', {
            headers: {
                "Authorization": "Bearer " + token,
                'Content-Type': 'application/json'
            }
        });
        
        const data = await res.json();
        
        if(data.error){
            notify(data.error, 'warning', 1000 * 5);
            return;
        }
        
        const container = document.getElementById('rooms');
        container.innerHTML = '';
        
        data.forEach(room => {
            const div = document.createElement('div');
            div.className = 'room';

            div.innerHTML = `
                <a href="#room_${room.room_id}" class="room-link">${room.name}</a>
            `;
                    
            container.appendChild(div);
        });
    }
    
    <!-- Событие на выбор комнаты -->
    document.addEventListener('click', (e) => {
        const link = e.target.closest('.room-link');

        if (link) {
            e.preventDefault();

            const hash = link.getAttribute('href').substring(1);
            const roomId = hash.replace('room_', '');

            // если хочешь — обновить URL
            window.location.hash = link.getAttribute('href');
            localStorage.setItem('room_id', roomId);
        }
    });
    
    document.addEventListener("DOMContentLoaded", () => {
        const token = localStorage.getItem('token');
        
        if (token === null) {
            window.location.href = '/';
        }
        
        joinedRooms(token);
        
        setInterval(() => {
            joinedRooms(token); // повтор каждые 60 секунд
        }, 60000);
    });
    
    function formEvent(id, params, onSuccess){
        const form = document.getElementById(id);
        const token = localStorage.getItem('token');
        var headers = {};
        
        if(params.send_token){
            headers["Authorization"] = "Bearer " + token
        }
        headers['Content-Type'] = 'application/json';
    
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const data = Object.fromEntries(new FormData(form).entries());
            
            if(params.type === 'text'){
                data.msgtype = 'm.text';
            }
            
            if(params.room){
                const hash = window.location.hash;
                const roomId = hash.replace('#room_', '');
                
                data.room_id = roomId;
            }

            const res = await fetch(params.url, {
                method: params.method,
                headers: headers,
                body: JSON.stringify(data)
            });

            const result = await res.json();

            form.reset(); // очистка формы
            joinedRooms(token);
            
            if (onSuccess) {
                onSuccess(result);
            }
        });
    }
    
    formEvent('formCreateRoom', {
        url: '/api/v1/createRoom/',
        method: 'POST',
        send_token: true
    },function(e){
        if(e.error){
            notify(e.error, 'warning', 3000 * 5);
            return;
        }
        
        const modalEl = document.getElementById('createRoom');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
    });
    
    formEvent('sendMessage', {
        url: '/api/v1/rooms/',
        method: 'POST',
        send_token: true,
        room: true,
        type: 'text',
    },function(e){
        console.log(e);
    });
</script>