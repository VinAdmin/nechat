<?php
use wco\forms\Form;

$this->title = 'Чат';
$fCreateRoom = new Form();
$fMessages = new Form();
$fInvite = new Form();
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
                    <div id="room-title" class="col-lg-9">{{ roomName }}</div>
                    <div class="col-lg-1">
                        <button class="btn btn-primary" data-bs-toggle="modal" @click.prevent="openMembers(roomId)" data-bs-target="#members">
                            Участники
                        </button>
                    </div>
                    <div class="col-lg-1" v-if="roomMembership === 'invite'">
                        <button class="btn btn-success" @click.prevent="acceptInvite">
                            Принять
                        </button>
                    </div>
                </div>
            </div>

            <div class="messages" ref="messages">
                <div v-for="msg in messages"
                     :class="['msg', isOwnMessage(msg) ? 'msg-own' : 'msg-other']">
                    <div class="msg-header">
                        <div class="msg-author" v-if="msg.json?.content?.sender">
                            {{ msg.json.content.sender }}
                        </div>
                        <div class="msg-time" v-if="formatTime(msg)">
                            {{ formatTime(msg) }}
                        </div>
                    </div>
                    <div class="msg-body">
                        <div v-if="msg.json?.content?.file_url">
                            <div v-if="msg.json.content.file_type?.startsWith('image/')">
                                <img :src="msg.json.content.file_url" :alt="msg.json.content.file_name || 'Изображение'" class="chat-image" @click.prevent="viewImage(msg.json.content.file_url, msg.json.content.file_name)" />
                            </div>
                            <div v-else>
                                <a :href="msg.json.content.file_url" target="_blank" rel="noreferrer">
                                    {{ msg.json.content.file_name || 'Файл' }}
                                </a>
                            </div>
                        </div>
                        <div v-if="msg.json?.content?.body">
                            {{ msg.json.content.body }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Форма -->
            <?=$fMessages->FormStart('sendMessage','POST', null, 'on', ['data' => true])?>
            <div class="messageComposer" v-show="roomId">
                <div class="mb-2">
                    <?=$fMessages->Input('text', 'body', '', ['class' => 'msgInput', 'placeholder' => 'Введите сообщение'])->Field()?>
                </div>
                <div class="mb-2 file-upload-wrapper">
                    <input type="file" name="file" id="file" class="file-input" @change="onFileChange" />
                    <label for="file" class="btn btn-outline-secondary file-upload-button">
                        <span>Прикрепить файл</span>
                    </label>
                    <span class="file-upload-name" v-if="fileName">{{ fileName }}</span>
                </div>
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
                    <h5 class="modal-title" id="createModalLabel">Создание комнаты</h5>
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
                    <h5 class="modal-title" id="membersModalLabel">Участники</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#invite">
                        Пригласить
                    </button>
                    
                    <div class="list-group">
                        <div v-for="member in roomMembers" class="list-group-item list-group-item-action list-group-item-primary d-flex justify-content-between align-items-center">
                            <span>
                                {{ member.user_id }}
                                <span v-if="member.membership === 'ban'" class="badge bg-danger ms-2">Забанен</span>
                            </span>
                            <button v-if="member.membership === 'ban'" class="btn btn-warning btn-sm" @click="unban(member.user_id)">Разбанить</button>
                            <button v-else class="btn btn-danger btn-sm" @click="ban(member.user_id)">Забанить</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Пригласить -->
    <div class="modal fade" id="invite" tabindex="-1" aria-labelledby="inviteLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Пригласить в {{ roomName }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <p>Пригласите кого-нибудь, используя его имя, имя пользователя (например, @username:<?=wco\kernel\WCO::$domain?>).</p>
                    <hr>
                    <?=$fInvite->FormStart('formInvite', 'POST')?>
                        <div class="row">
                            <div class="col-md-8"><?=$fCreateRoom->Input('text', 'user_id')->Field()?></div>
                            <div class="col-md-4">
                                <?=$fInvite->Input(Form::INPUT_SUBMIT, 'sendInvite', 'Пригласить', [
                                    'class' => 'btn btn-primary'
                                ])->Field()?>
                            </div>
                        </div>
                    <?=$fInvite->FormEnd()?>
                </div>
                <div class="modal-footer">
                        
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="imagePreviewModalLabel">{{ previewImageName }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex justify-content-center align-items-center p-0">
                    <img :src="previewImage" :alt="previewImageName || 'Изображение'" class="image-preview" />
                </div>
            </div>
        </div>
    </div>

</div>