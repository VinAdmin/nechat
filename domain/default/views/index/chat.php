<?php
use wco\forms\Form;

$this->title = 'Чат';
$fCreateRoom = new Form();
$fMessages = new Form();
$fInvite = new Form();
?>
<div id="app">

    <div id="notify"></div>

    <button class="rooms-toggle btn btn-dark" @click="showRooms = !showRooms" :class="{ active: showRooms }">
        ☰
    </button>

    <div class="prog_chat">
        
        <!-- Левая панель -->
        <div class="rooms_board" :class="{ visible: showRooms }">
            <div class="rooms-header d-flex gap-1 p-1 align-items-center">
                <button class="btn btn-primary btn-sm flex-grow-1" data-bs-toggle="modal" data-bs-target="#createRoom">
                    Добавить
                </button>
                <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#publicRooms" title="Публичные комнаты">
                    🔍
                </button>
                <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#dmModal" title="Личные сообщения">
                    ✉
                </button>
                <button class="btn btn-outline-info btn-sm" @click="openProfile" data-bs-toggle="modal" data-bs-target="#profileModal" title="Профиль">
                    👤
                </button>
                <button class="btn btn-outline-danger btn-sm" @click="logout" title="Выйти">
                    <i class="fa fa-power-off" aria-hidden="true"></i>
                </button>
            </div>

            <div class="rooms">
                <div v-for="room in rooms"
                     :key="room.room_id"
                     class="room">

                    <a href="#"
                       class="room-link"
                       @click.prevent="openRoom(room); showRooms = false">
                        <span class="room-avatar-sm-wrapper">
                            <img v-if="room.avatar_url" :src="room.avatar_url" class="room-avatar-sm" alt="" />
                            <span v-else class="room-avatar-sm room-avatar-placeholder">{{ room.name.charAt(0) }}</span>
                            <span v-if="isOnline(room.creator)" class="online-dot"></span>
                        </span>
                        {{ room.name }}
                        <span v-if="unreadCounts[room.room_id]" class="unread-badge">{{ unreadCounts[room.room_id] }}</span>
                    </a>

                </div>
            </div>
        </div>

        <!-- Затемнение для мобильной версии -->
        <div class="rooms-overlay d-md-none" :class="{ visible: showRooms }" @click="showRooms = false"></div>

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
                        <button class="btn btn-outline-secondary btn-sm me-1" title="Поиск в чате" @click="toggleSearch" v-if="roomId && roomMembership === 'join'">
                            🔍
                        </button>
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

            <div class="messages">
                <div class="list" ref="messages">
                <div v-for="(msg, index) in messages" :key="msg.event_id">
                    <div v-if="showDateSeparator(msg, index)" class="msg-date-separator">{{ msgDate(msg) }}</div>
                    <div v-if="msg.type === 'm.room.member' && msg.json?.content?.membership === 'join'" class="msg-system">
                        <span class="msg-system-text">{{ msg.json.content.displayname || msg.json.sender }} присоединился</span>
                        <span class="msg-system-time">{{ formatTime(msg) }}</span>
                    </div>
                    <div v-else-if="msg.json?.content?.deleted" :data-event-id="msg.event_id" :class="['msg', 'msg-deleted-row', isOwnMessage(msg) ? 'msg-own' : 'msg-other']">
                        <div class="msg-bubble msg-bubble-deleted">
                            <span class="msg-deleted-text">Сообщение удалено</span>
                        </div>
                    </div>
                    <div v-else :data-event-id="msg.event_id" :class="['msg', isOwnMessage(msg) ? 'msg-own' : 'msg-other']">
                        <div v-if="!isOwnMessage(msg)" class="msg-avatar">
                            <img v-if="msg.json?.content?.avatar_url && !isSameSender(index)" :src="msg.json.content.avatar_url" class="msg-avatar-img" alt="" />
                            <span v-else-if="!isSameSender(index)" class="msg-avatar-placeholder">{{ (msg.json?.content?.sender || '?').charAt(1).toUpperCase() }}</span>
                        </div>
                        <div class="msg-content-wrap">
                        <div v-if="!isSameSender(index) && !isOwnMessage(msg) && msg.json?.content?.sender" class="msg-sender-label">{{ msg.json.content.sender }}</div>
                        <div class="msg-bubble" :class="{ 'msg-has-reply': msg.json?.content?.reply_to }">
                            <div v-if="msg.json?.content?.reply_to" class="reply-context" @click="scrollToMessage(msg.json.content.reply_to.event_id)">
                                <div class="reply-context-sender">{{ msg.json.content.reply_to.sender || '?' }}</div>
                                <div class="reply-context-body">{{ msg.json.content.reply_to.body || '...' }}</div>
                            </div>
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
                                    <span class="voice-label" v-if="msg.json.content.file_name?.startsWith('voice_')">🎤</span>
                                    <audio :src="msg.json.content.file_url" class="chat-audio" controls></audio>
                                </div>
                                <div v-else class="msg-file-attach">
                                    <a :href="msg.json.content.file_url" target="_blank" rel="noreferrer">
                                        {{ msg.json.content.file_name || 'Файл' }}
                                    </a>
                                </div>
                            </div>
                            <div v-if="msg.json?.content?.body" class="msg-text">{{ msg.json.content.body }} <span v-if="msg.json.content.edited" class="msg-edited">(ред.)</span></div>
                            <div class="msg-meta">
                                <span class="msg-time">{{ formatTime(msg) }}</span>
                                <span v-if="isOwnMessage(msg)" class="msg-check">✓✓</span>
                            </div>
                        </div>
                        <div class="msg-actions">
                            <button v-if="!msg.json?.content?.deleted && isOwnMessage(msg)" class="btn-reply" @click="startEdit(msg)" title="Редактировать" style="color:#ffc107;">✎</button>
                            <button v-if="!msg.json?.content?.deleted" class="btn-reply" @click="setReply(msg)" title="Ответить">↩</button>
                            <button v-if="(isOwnMessage(msg) || isRoomOwner()) && !msg.json?.content?.deleted" class="btn-delete" @click="deleteMessage(msg.event_id)" title="Удалить">✕</button>
                        </div>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Поиск в чате -->
                <div v-if="showSearch" class="search-panel">
                    <div class="search-input-row">
                        <input type="text" v-model="searchQuery" class="form-control form-control-sm" placeholder="Поиск сообщений..." @keyup.enter="searchMessages" />
                        <button class="btn btn-sm btn-primary" @click="searchMessages" :disabled="searchLoading">Найти</button>
                        <button class="btn btn-sm btn-outline-secondary" @click="toggleSearch">✕</button>
                    </div>
                    <div v-if="searchLoading" class="search-loading">Загрузка...</div>
                    <div v-if="!searchLoading && searchResults.length === 0 && searchQuery.length > 0" class="search-empty">Ничего не найдено</div>
                    <div v-if="searchResults.length > 0" class="search-results">
                        <div v-for="r in searchResults" :key="r.event_id" class="search-result-item" @click="scrollToMessage(r.event_id)">
                            <span class="search-result-sender">{{ r.json?.sender }}</span>
                            <span class="search-result-body">{{ r.json?.content?.body }}</span>
                        </div>
                    </div>
                </div>

                <!-- Индикатор набора текста -->
                <div v-if="typingUsers.length > 0" class="typing-indicator">
                    <span class="typing-dots"><span>.</span><span>.</span><span>.</span></span>
                    <span class="typing-text">{{ typingUsers.join(', ') }} {{ typingUsers.length === 1 ? 'набирает текст' : 'набирают текст' }}</span>
                </div>

                <!-- Форма -->
                <?=$fMessages->FormStart('sendMessage','POST', null, 'on', ['data' => true])?>
                <div class="messageComposer" v-show="roomId && roomMembership === 'join'">
                    <div v-if="replyTo" class="reply-indicator">
                        <span class="reply-indicator-text">Ответ {{ replyTo.sender }}: {{ replyTo.body }}</span>
                        <button type="button" class="btn-close btn-close-white btn-sm" @click="cancelReply" aria-label="Отменить"></button>
                    </div>
                    <div v-if="editingMessage" class="reply-indicator edit-indicator" style="border-color: rgba(255,200,0,0.3);">
                        <span class="reply-indicator-text" style="color: #ffc107;">Редактирование сообщения</span>
                        <button type="button" class="btn-close btn-close-white btn-sm" @click="cancelEdit" aria-label="Отменить"></button>
                    </div>
                    <!-- Emoji picker -->
                    <div v-if="showEmojiPicker" class="emoji-picker">
                        <div class="emoji-categories">
                            <div v-for="(emojis, cat) in emojiCategories" :key="cat" class="emoji-category">
                                <div class="emoji-cat-title">{{ cat }}</div>
                                <div class="emoji-grid">
                                    <span v-for="e in emojis" :key="e" class="emoji-item" @click="insertEmoji(e)">{{ e }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="editingMessage" class="composer-input-row">
                        <textarea id="editInput" v-model="editBody" class="msgInput edit-input" placeholder="Введите новый текст..." rows="1" @keydown="onEditKeydown" @input="$event.target.style.height='auto'; $event.target.style.height=$event.target.scrollHeight+'px'"></textarea>
                        <button type="button" class="btn btn-warning" @click="saveEdit">✔</button>
                        <button type="button" class="btn btn-outline-secondary" @click="cancelEdit">✕</button>
                    </div>
                    <div v-else class="composer-input-row">
                        <textarea name="body" class="msgInput" placeholder="Введите сообщение" rows="1" @keydown="onBodyKeydown" @input="resizeTextarea; onTypingInput()" ref="bodyInput"></textarea>
                        <?=$fMessages->Input(Form::INPUT_SUBMIT, 'send', '➤', [
                            'class' => 'btn btn-primary'
                        ])->Field()?>
                    </div>
                <div class="composer-actions">
                    <div v-if="uploadProgress !== null" class="upload-progress-bar">
                        <div class="upload-progress-track">
                            <div class="upload-progress-fill" :style="{ width: uploadProgress + '%' }"></div>
                        </div>
                        <span class="upload-progress-text">{{ uploadProgress }}%</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline-secondary file-upload-button" @click="toggleEmoji" title="Эмодзи">
                            <span>😊</span>
                        </button>
                    </div>
                    <div class="file-upload-wrapper">
                        <input type="file" name="file" id="file" class="file-input" @change="onFileChange" accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" />
                        <label for="file" class="btn btn-outline-secondary file-upload-button" title="Прикрепить файл">
                            <span>📎</span>
                        </label>
                        <span class="file-upload-name" v-if="fileName">{{ fileName }}</span>
                    </div>
                    <div>
                        <input type="file" name="video_file" id="video" class="file-input" accept="video/*" @change="onVideoChange" />
                        <label for="video" class="btn btn-outline-secondary file-upload-button" title="Видео">
                            <span>🎬</span>
                        </label>
                    </div>
                    <div>
                        <input type="file" name="audio_file" id="audio" class="file-input" accept="audio/*" @change="onAudioChange" />
                        <label for="audio" class="btn btn-outline-secondary file-upload-button" title="Аудио">
                            <span>🎵</span>
                        </label>
                    </div>
                    <div>
                        <template v-if="!voiceRecording && !voiceBlob">
                            <button type="button" class="btn btn-outline-secondary file-upload-button" @click="startVoice" title="Голосовое сообщение">
                                <span>🎤</span>
                            </button>
                        </template>
                        <template v-else-if="voiceRecording">
                            <button type="button" class="btn btn-danger file-upload-button" @click="stopVoice" title="Остановить запись">
                                <span>⏹</span>
                            </button>
                            <span class="voice-timer ms-1">{{ formatVoiceTime(voiceSeconds) }}</span>
                        </template>
                        <template v-else-if="voiceBlob && !voiceRecording">
                            <button type="button" class="btn btn-success file-upload-button" @click="sendVoice" title="Отправить">
                                <span>➤</span>
                            </button>
                            <button type="button" class="btn btn-outline-secondary file-upload-button" @click="cancelVoice" title="Отмена">
                                <span>✕</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            <?=$fMessages->FormEnd();?>
        </div>

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

    <!-- Профиль -->
    <div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profileModalLabel">Профиль</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <img v-if="profileAvatar" :src="profileAvatar" class="profile-avatar-preview" alt="" />
                        <div v-else class="profile-avatar-preview profile-avatar-placeholder">{{ (profileUserId || '?').charAt(1).toUpperCase() }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white">ID</label>
                        <input type="text" class="form-control" :value="profileUserId" readonly />
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white">Аватар</label>
                        <input type="file" accept="image/*" @change="onProfileAvatarChange" class="form-control" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white">Старый пароль</label>
                        <input type="password" v-model="profileOldPassword" class="form-control" placeholder="Введите текущий пароль" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white">Новый пароль</label>
                        <input type="password" v-model="profilePassword" class="form-control" placeholder="Оставьте пустым, если не хотите менять" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-white">Токен доступа</label>
                        <div class="input-group">
                            <input type="text" class="form-control" :value="profileToken" readonly id="tokenField" />
                            <button class="btn btn-outline-secondary" @click="copyToken" title="Копировать">📋</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                    <button type="button" class="btn btn-primary" @click="saveProfile">Сохранить</button>
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
                                <span v-if="isOnline(member.user_id)" class="online-dot-inline"></span>
                                {{ member.user_id }}
                                <span v-if="member.membership === 'ban'" class="badge bg-danger ms-2">Забанен</span>
                            </span>
                            <span v-if="isRoomOwner() && member.user_id !== roomCreator" class="d-flex gap-1">
                                <button v-if="member.membership === 'ban'" class="btn btn-warning btn-sm" @click="unban(member.user_id)">Разбанить</button>
                                <template v-else>
                                    <button class="btn btn-outline-warning btn-sm" @click="kick(member.user_id)" title="Выгнать (можно вернуться)">Выгнать</button>
                                    <button class="btn btn-danger btn-sm" @click="ban(member.user_id)" title="Забанить (навсегда)">Забанить</button>
                                </template>
                            </span>
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

    <!-- Личные сообщения -->
    <div class="modal fade" id="dmModal" tabindex="-1" aria-labelledby="dmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dmModalLabel">Начать личный диалог</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Введите user_id пользователя (например, @username:<?=wco\kernel\WCO::$domain?>)</p>
                    <div class="input-group">
                        <input type="text" v-model="dmUserId" class="form-control" placeholder="@user:<?=wco\kernel\WCO::$domain?>" @keyup.enter="startDM" />
                        <button class="btn btn-primary" @click="startDM" :disabled="!dmUserId.trim()">Открыть</button>
                    </div>
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
