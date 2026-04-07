<?php
use wco\forms\Form;

$this->title = 'Чат';
$fCreateRoom = new Form();
?>
<div id="notify"></div>

<div>
    <div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRoom">
                Добавить
            </button>
        </div>
        
        <div class="rooms" id="rooms">
            
        </div>
    </div>
    
    <div class="chat">
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
    
    const hash = window.location.hash.substring(1);

    const roomId = hash.replace('room_', '');
    console.log(roomId);
    
    async function joinedRooms(token) {
        const res = await fetch('/api/v1/joined_rooms/', {
            headers: {
                "Authorization": "Bearer " + token,
                'Content-Type': 'application/json'
            }
        });
        
        const data = await res.json();
        
        if(data.error){
            notify(data.error, 'warning', 3000 * 5);
            return;
        }
        
        console.log(data);
        
        const container = document.getElementById('rooms');
        container.innerHTML = '';
        
        data.forEach(room => {
            const div = document.createElement('div');
            div.className = 'room';

            div.innerHTML = `
                <a href="#room_${room.room_id}" onclick>${room.name}</a>
            `;
                    
            container.appendChild(div);
        });
    }
    
    document.addEventListener('click', (e) => {
        const link = e.target.closest('.room-link');

        if (link) {
            e.preventDefault();

            const roomId = link.getAttribute('href').substring(1);
            console.log(roomId);
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
    
    const form = document.getElementById('formCreateRoom');
    
    form.addEventListener('submit', async (e) => {
        const token = localStorage.getItem('token');
        e.preventDefault();

        const data = Object.fromEntries(new FormData(form).entries());

        const res = await fetch('/api/v1/createRoom/', {
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
        
        const modalEl = document.getElementById('createRoom');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
        form.reset(); // очистка формы
        joinedRooms(token);

        return;
    });
</script>