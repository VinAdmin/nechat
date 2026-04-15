<?php
use wco\forms\Form;

$this->title = 'Чат';
$fCreateRoom = new Form();
$fMessages = new Form();
?>
<div id="app">

    <div id="notify"></div>

    <div class="prog_chat">
        
        <!-- Левая панель -->
        <div class="rooms_board">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createRoom">
                Добавить
            </button>

            <div class="rooms">
                <div v-for="room in rooms"
                     :key="room.room_id"
                     class="room">

                    <a href="#"
                       class="room-link"
                       @click.prevent="openRoom(room)">
                        {{ room.name }}
                    </a>

                </div>
            </div>
        </div>

        <!-- Чат -->
        <div class="chat">
            <div class="room">
                <div class="row">
                    <div id="room-title" class="col-lg-11">{{ roomName }}</div>
                    <div class="col-lg-1">
                        <button class="btn btn-primary" data-bs-toggle="modal" @click.prevent="openMembers(roomId)" data-bs-target="#members">
                            Участники
                        </button>
                    </div>
                </div>
            </div>

            <div class="messages" ref="messages">
                <div v-for="msg in messages"
                     class="msg">
                    {{ msg.json?.content?.body }}
                </div>
            </div>

            <!-- Форма -->
            <?=$fMessages->FormStart('sendMessage')?>
            <div class="messageComposer" v-show="roomId">
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
    
    <!-- Участники -->
    <div class="modal fade" id="members" tabindex="-1" aria-labelledby="membersLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Участники</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="list-group">
                        <a v-for="member in roomMembers" href="#" class="list-group-item list-group-item-action list-group-item-primary">{{ member.user_id }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>