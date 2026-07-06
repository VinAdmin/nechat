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
            <div class="d-flex gap-1 p-1">
                <button class="btn btn-primary btn-sm flex-grow-1" data-bs-toggle="modal" data-bs-target="#createRoom">
                    Добавить
                </button>
                <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#publicRooms" title="Публичные комнаты">
                    🔍
                </button>
                <button class="btn btn-outline-danger btn-sm" @click="logout" title="Выйти">
                    ⏻
                </button>
            </div>

            <div class="rooms">
                <div v-for="room in rooms"
                     :key="room.room_id"
                     class="room">

                    <a href="#"
                       class="room-link"
                       @click.prevent="openRoom(room)">
                        <img v-if="room.avatar_url" :src="room.avatar_url" class="room-avatar-sm" alt="" />
                        <span v-else class="room-avatar-sm room-avatar-placeholder">{{ room.name.charAt(0) }}</span>
                        {{ room.name }}
                    </a>

                </div>
            </div>
        </div>

        <!-- Чат -->
        <div class="chat">
            <div class="room">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <img v-if="roomAvatar" :src="roomAvatar" class="room-avatar-md" alt="" />
                        <span v-else class="room-avatar-md room-avatar-placeholder">{{ roomName.charAt(0) }}</span>
                    </div>
                    <div class="col">
                        <div id="room-title" class="room-title-text">{{ roomName }}</div>
                        <div v-if="roomTopic" class="room-topic">{{ roomTopic }}</div>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-secondary btn-sm me-1" title="Настройки комнаты" @click.prevent="openRoomSettings" data-bs-toggle="modal" data-bs-target="#roomSettings" v-if="roomId && roomMembership === 'join'">
                            ⚙
                        </button>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" @click.prevent="openMembers(roomId)" data-bs-target="#members" v-if="roomId && roomMembership === 'join'">
                            Участники
                        </button>
                    </div>
                    <div class="col-auto" v-if="roomMembership === 'invite'">
                        <button class="btn btn-success btn-sm" @click.prevent="acceptInvite">
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
                            <div v-else-if="isVideo(msg.json.content.file_type, msg.json.content.file_name)" class="video-wrap">
                                <div class="video-thumb" @click="viewVideo(msg.json.content.file_url, msg.json.content.file_name)">
                                    <video :src="msg.json.content.file_url" class="chat-video" preload="metadata" @error="onVideoError($event, msg.json.content.file_url, msg.json.content.file_name)"></video>
                                    <div class="video-play-overlay">
                                        <span class="video-play-icon">▶</span>
                                    </div>
                                </div>
                            </div>
                            <div v-else-if="isAudio(msg.json.content.file_type, msg.json.content.file_name)" class="audio-wrap">
                                <audio :src="msg.json.content.file_url" class="chat-audio" controls></audio>
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
            <div class="messageComposer" v-show="roomId && roomMembership === 'join'">
                <div class="mb-2">
                    <?=$fMessages->Input('text', 'body', '', ['class' => 'msgInput', 'placeholder' => 'Введите сообщение'])->Field()?>
                </div>
                <div class="mb-2 file-upload-wrapper">
                    <input type="file" name="file" id="file" class="file-input" @change="onFileChange" accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" />
                    <label for="file" class="btn btn-outline-secondary file-upload-button">
                        <span>Прикрепить файл</span>
                    </label>
                    <span class="file-upload-name" v-if="fileName">{{ fileName }}</span>
                </div>
                <div class="mb-2">
                    <input type="file" name="video_file" id="video" class="file-input" accept="video/*" @change="onVideoChange" />
                    <label for="video" class="btn btn-outline-secondary file-upload-button">
                        <span>Видео</span>
                    </label>
                </div>
                <div class="mb-2">
                    <input type="file" name="audio_file" id="audio" class="file-input" accept="audio/*" @change="onAudioChange" />
                    <label for="audio" class="btn btn-outline-secondary file-upload-button">
                        <span>Аудио</span>
                    </label>
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
                    <div class="mb-3">
                        <label>Название комнаты</label>
                        <?=$fCreateRoom->Input('text', 'name', '', ['class' => 'form-control'])->Field()?>
                    </div>
                    <div class="mb-3">
                        <label>Тема комнаты</label>
                        <textarea name="topic" class="form-control" rows="2" placeholder="О чём эта комната?"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Доступ</label>
                        <select name="join_rule" class="form-control">
                            <option value="public">Открытая (любой может войти)</option>
                            <option value="invite">Закрытая (только по приглашению)</option>
                        </select>
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
    
    <!-- Поиск публичных комнат -->
    <div class="modal fade" id="publicRooms" tabindex="-1" aria-labelledby="publicRoomsLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="publicRoomsLabel">Публичные комнаты</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="input-group mb-3">
                        <input type="text" v-model="publicSearchQuery" class="form-control" placeholder="Поиск комнат..." @keyup.enter="searchPublicRooms" />
                        <button class="btn btn-primary" @click="searchPublicRooms">Поиск</button>
                    </div>
                    <div v-if="publicSearchLoading" class="text-center text-muted py-4">
                        Загрузка...
                    </div>
                    <div v-if="!publicSearchLoading && publicRooms.length === 0 && publicSearchSearched" class="text-center text-muted py-4">
                        Комнат не найдено
                    </div>
                    <div v-for="room in publicRooms" :key="room.room_id" class="list-group-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <img v-if="room.avatar_url" :src="room.avatar_url" class="room-avatar-sm" alt="" />
                            <span v-else class="room-avatar-sm room-avatar-placeholder">{{ room.name.charAt(0) }}</span>
                            <div>
                                <div class="fw-bold">{{ room.name }}</div>
                                <div v-if="room.topic" class="small text-muted">{{ room.topic }}</div>
                                <div class="small text-muted">Создатель: {{ room.creator }}</div>
                            </div>
                        </div>
                        <button class="btn btn-success btn-sm" @click="joinPublicRoom(room.room_id)">Войти</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Настройки комнаты -->
    <div class="modal fade" id="roomSettings" tabindex="-1" aria-labelledby="roomSettingsLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="roomSettingsLabel">Настройки комнаты: {{ roomName }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Название комнаты</label>
                        <input type="text" v-model="settingsName" class="form-control" />
                    </div>
                    <div class="mb-3">
                        <label>Тема комнаты</label>
                        <textarea v-model="settingsTopic" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Аватар комнаты</label>
                        <div v-if="settingsAvatar" class="mb-2">
                            <img :src="settingsAvatar" class="room-avatar-preview" alt="" />
                        </div>
                        <input type="file" accept="image/*" @change="onAvatarChange" class="form-control" />
                    </div>
                    <div class="mb-3">
                        <label>Доступ</label>
                        <select v-model="settingsJoinRule" class="form-control">
                            <option value="public">Открытая (любой может войти)</option>
                            <option value="invite">Закрытая (только по приглашению)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" @click="saveRoomSettings">Сохранить</button>
                </div>
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
                    
                    <div class="list-group mt-2">
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
                            <div class="col-md-8"><?=$fCreateRoom->Input('text', 'user_id', '', ['class' => 'form-control'])->Field()?></div>
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

    <div class="modal fade" id="videoPreviewModal" tabindex="-1" aria-labelledby="videoPreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="videoPreviewModalLabel">{{ previewVideoName }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex justify-content-center align-items-center p-0">
                    <video :src="previewVideo" class="video-preview" controls autoplay></video>
                </div>
            </div>
        </div>
    </div>

</div>
