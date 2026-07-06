 /**
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 **/
 
/**
 * Vue-приложение для управления интерфейсом чата и данными.
 */
const app = Vue.createApp({
    /**
     * Реактивное состояние для приложения чата.
     * @returns {{rooms: Array, messagesStore: Object, messages: Array, roomId: string|null, roomName: string, roomMembership: string|null, syncToken: string, roomMembers: Array}}
     */
    data() {
        return {
            rooms: [],
            messagesStore: {},
            messages: [],
            roomId: null,
            roomName: '',
            roomTopic: '',
            roomAvatar: '',
            roomJoinRule: 'public',
            roomMembership: null,
            syncToken: "",
            roomMembers: [],
            fileName: '',
            previewImage: '',
            previewImageName: '',
            previewVideo: '',
            previewVideoName: '',
            settingsName: '',
            settingsTopic: '',
            settingsAvatar: '',
            settingsJoinRule: 'public',
            settingsAvatarFile: null,
            publicRooms: [],
            publicSearchQuery: '',
            publicSearchSearched: false,
            publicSearchLoading: false
        }
    },

    methods: {

        /**
         * Загружает список комнат, в которые вошёл текущий пользователь.
         * Обновляет локальный массив комнат и состояние участия пользователя.
         * @returns {Promise<void>}
         */
        async joinedRooms() {
            const token = localStorage.getItem('token');

            // Запрашиваем комнаты, в которых состоит текущий пользователь.
            const res = await fetch('/api/v1/joined_rooms/', {
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                }
            });

            const data = await res.json();

            if (data.error) {
                notify(data.error, 'warning', 4000);
                window.location.href = '/';
                return;
            }

            this.rooms = data;

            if (this.roomId) {
                const currentRoom = this.rooms.find(room => room.room_id === this.roomId);
                this.roomMembership = currentRoom ? currentRoom.membership : this.roomMembership;
            }

            if (data.length === 0) {
                history.replaceState(null, null, window.location.pathname);
            }
        },

        /**
         * Выбирает и открывает комнату из списка.
         * @param {Object} room
         * @param {string} room.room_id
         * @param {string} room.name
         * @param {string} [room.membership]
         */
        openRoom(room) {
            this.roomId = room.room_id;
            this.roomName = room.name;
            this.roomTopic = room.topic || '';
            this.roomAvatar = room.avatar_url || '';
            this.roomJoinRule = room.join_rule || 'public';
            this.roomMembership = room.membership || null;

            localStorage.setItem('room_id', room.room_id);
            localStorage.setItem('room_name', room.name);

            window.location.hash = `#room_${room.room_id}`;

            this.updateMessages();
        },
        
        /**
         * Создаёт новую комнату через запрос API и обновляет список комнат.
         * @param {SubmitEvent} e
         * @returns {Promise<void>}
         */
        async createRoom(e){
            e.preventDefault();
            
            const form = e.target;
            const token = localStorage.getItem('token');

            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            if (!data.topic) data.topic = '';
            if (!data.join_rule) data.join_rule = 'public';

            const res = await fetch('/api/v1/createRoom/', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await res.json();

            if (result.error) {
                notify(result.error, 'warning', 5000);
                return;
            }
            
            this.joinedRooms();
            const modalEl = document.getElementById('createRoom');
            const modal = bootstrap.Modal.getInstance(modalEl);
            modal.hide();
            form.reset();
        },
        
        /**
         * Проверяет, находится ли контейнер сообщений внизу.
         * @returns {boolean}
         */
        isAtBottom() {
            const el = this.$refs.messages;
            if (!el) return true;

            return el.scrollHeight - el.scrollTop - el.clientHeight < 50;
        },

        /**
         * Прокручивает контейнер сообщений вниз.
         * @param {boolean} [smooth=true]
         */
        scrollToBottom(smooth = true) {
            const el = this.$refs.messages;
            if (!el) return;

            el.scrollTo({
                top: el.scrollHeight,
                behavior: smooth ? 'smooth' : 'auto'
            });
        },

        /**
         * Обновляет отображаемый массив сообщений из кеша для активной комнаты.
         */
        updateMessages() {
            const shouldScroll = this.isAtBottom();
            
            if (!this.messagesStore[this.roomId]) {
                this.messages = [];
                return;
            }
            
            this.messages = this.messagesStore[this.roomId].filter(m =>
                m.json?.content?.body
            );
    
            this.$nextTick(() => {
                if (shouldScroll) {
                    this.scrollToBottom();
                }
            });
        },

        /**
         * Опрос сервера на новые события / сообщения и слияние их в локальный кеш.
         * @returns {Promise<void>}
         */
        async sync() {
            const token = localStorage.getItem('token');

            const res = await fetch('/api/v1/sync/?since=' + this.syncToken, {
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                }
            });

            const data = await res.json();
            
            if (data.error) {
                notify(data.error, 'warning', 5000);
                localStorage.clear();
                window.location.href = '/';
                return;
            }
            
            const rooms = data.rooms?.join || {};
            
            for(const roomId in rooms){
                const events = rooms[roomId].events || {};
                
                if (!this.messagesStore[roomId]) {
                    this.messagesStore[roomId] = [];
                }
                
                for(const event of events){
                    if (!event?.event_id) continue;
                    this.messagesStore[roomId].push(event);
                }
            }

            const invite = data.rooms?.invite || {};
            for(const roomId in invite){
                const events = invite[roomId].invite_state.events || {};
                
                if (!this.messagesStore[roomId]) {
                    this.messagesStore[roomId] = [];
                }
                
                for(const event of events){
                    if (!event?.event_id) continue;
                    this.messagesStore[roomId].push(event);
                }
            }
            
            this.syncToken = data.next_batch || this.syncToken;
            
            sessionStorage.setItem("sync", this.syncToken);

            this.updateMessages();
        },

        /**
         * Анализирует текущий хеш локации и восстанавливает состояние выбранной комнаты.
         */
        parseHash() {
            const hash = window.location.hash.substring(1);
            const params = new URLSearchParams(hash);

            const id = params.get('room');
            const name = params.get('name');

            if (id) {
                this.roomId = id;
                this.roomName = name || localStorage.getItem('room_name');
                const currentRoom = this.rooms.find(room => room.room_id === id);
                if (currentRoom) {
                    this.roomName = currentRoom.name;
                    this.roomTopic = currentRoom.topic || '';
                    this.roomAvatar = currentRoom.avatar_url || '';
                    this.roomJoinRule = currentRoom.join_rule || 'public';
                    this.roomMembership = currentRoom.membership || null;
                }

                this.updateMessages();
            }
        },

        /**
         * Отправляет текстовое сообщение в текущую открытую комнату.
         * @param {SubmitEvent} e
         * @returns {Promise<void>}
         */
        async sendMessage(e) {
            e.preventDefault();

            const form = e.target;
            const token = localStorage.getItem('token');
            const fileInput = form.querySelector('input[name="file"]');
            const videoInput = form.querySelector('input[name="video_file"]');
            const file = fileInput?.files?.[0] || videoInput?.files?.[0];
            const bodyText = form.querySelector('input[name="body"]')?.value || '';

            if (!file && !bodyText.trim()) {
                notify('Введите сообщение или выберите файл', 'warning', 4000);
                return;
            }

            if (file && file.size > 1 * 1024 * 1024) {
                await this.uploadFileInChunks({
                    form,
                    token,
                    file,
                    bodyText,
                    roomId: this.roomId
                });
            } else {
                const formData = new FormData(form);
                formData.delete('video_file');
                formData.set('room_id', this.roomId);
                formData.set('msgtype', file ? 'm.file' : 'm.text');

                if (file && !formData.has('file')) {
                    formData.append('file', file, file.name);
                }

                const res = await fetch('/api/v1/rooms/', {
                    method: 'POST',
                    headers: {
                        "Authorization": "Bearer " + token
                    },
                    body: formData
                });

                const result = await res.json();

                if (result.error) {
                    notify(result.error, 'warning', 5000);
                    return;
                }
            }

            form.reset();
            this.fileName = '';
        },

        async uploadFileInChunks({form, token, file, bodyText, roomId}) {
            const chunkSize = 1 * 1024 * 1024; // 1 MB
            const chunkCount = Math.ceil(file.size / chunkSize);
            const uploadId = `${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;

            for (let index = 1; index <= chunkCount; index++) {
                const chunk = file.slice((index - 1) * chunkSize, index * chunkSize);
                const formData = new FormData();
                formData.append('room_id', roomId);
                formData.append('msgtype', 'm.file');
                formData.append('upload_id', uploadId);
                formData.append('chunk_index', index);
                formData.append('chunk_count', chunkCount);
                formData.append('file_name', file.name);
                formData.append('file_size', file.size);
                if (index === chunkCount && bodyText) {
                    formData.append('body', bodyText);
                }
                formData.append('file', chunk, file.name);

                const res = await fetch('/api/v1/rooms/', {
                    method: 'POST',
                    headers: {
                        "Authorization": "Bearer " + token
                    },
                    body: formData
                });

                const result = await res.json();
                if (result.error) {
                    notify(result.error, 'warning', 5000);
                    throw new Error(result.error);
                }

                if (result.status === 'error') {
                    notify(result.error || 'Ошибка загрузки чанка', 'warning', 5000);
                    throw new Error(result.error || 'Upload error');
                }
            }
        },

        /**
         * Обновляет название выбранного файла в интерфейсе.
         * @param {Event} e
         */
        onFileChange(e) {
            const file = e.target.files?.[0];
            this.fileName = file ? file.name : '';
        },

        /**
         * Обрабатывает выбор видеофайла и отправляет его в чат.
         * @param {Event} e
         * @returns {Promise<void>}
         */
        async onVideoChange(e) {
            const file = e.target.files?.[0];
            if (!file) return;

            const form = document.getElementById('sendMessage');
            const token = localStorage.getItem('token');

            if (file.size > 1 * 1024 * 1024) {
                await this.uploadFileInChunks({
                    form,
                    token,
                    file,
                    bodyText: file.name,
                    roomId: this.roomId
                });
            } else {
                const formData = new FormData();
                formData.append('room_id', this.roomId);
                formData.append('msgtype', 'm.file');
                formData.append('file', file, file.name);

                const res = await fetch('/api/v1/rooms/', {
                    method: 'POST',
                    headers: {
                        "Authorization": "Bearer " + token
                    },
                    body: formData
                });

                const result = await res.json();

                if (result.error) {
                    notify(result.error, 'warning', 5000);
                    return;
                }
            }

            e.target.value = '';
        },

        async onAudioChange(e) {
            const file = e.target.files?.[0];
            if (!file) return;

            const form = document.getElementById('sendMessage');
            const token = localStorage.getItem('token');

            if (file.size > 1 * 1024 * 1024) {
                await this.uploadFileInChunks({
                    form,
                    token,
                    file,
                    bodyText: file.name,
                    roomId: this.roomId
                });
            } else {
                const formData = new FormData();
                formData.append('room_id', this.roomId);
                formData.append('msgtype', 'm.file');
                formData.append('file', file, file.name);

                const res = await fetch('/api/v1/rooms/', {
                    method: 'POST',
                    headers: {
                        "Authorization": "Bearer " + token
                    },
                    body: formData
                });

                const result = await res.json();

                if (result.error) {
                    notify(result.error, 'warning', 5000);
                    return;
                }
            }

            e.target.value = '';
        },

        /**
         * Показывает увеличенное изображение в модальном окне.
         * @param {string} url
         * @param {string} name
         */
        viewImage(url, name) {
            this.previewImage = url;
            this.previewImageName = name || 'Изображение';

            const modalEl = document.getElementById('imagePreviewModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        },

        /**
         * Открывает видео в модальном окне для полноэкранного просмотра.
         * @param {string} url
         * @param {string} name
         */
        async viewVideo(url, name) {
            this.previewVideo = url;
            this.previewVideoName = name || 'Видео';

            const modalEl = document.getElementById('videoPreviewModal');
            if (!modalEl) return;

            await this.$nextTick();
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        },

        /**
         * Обрабатывает ошибку воспроизведения видео — показывает ссылку на скачивание.
         * @param {Event} event
         * @param {string} url
         * @param {string} name
         */
        onVideoError(event, url, name) {
            const video = event.target;
            if (!video?.parentNode) return;
            const link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.rel = 'noreferrer';
            link.textContent = name || 'Скачать видео';
            link.className = 'btn btn-sm btn-outline-light mt-1';
            video.parentNode.insertBefore(link, video.nextSibling);
            video.remove();
        },

        /**
         * Определяет, является ли файл видео по MIME-типу или расширению.
         * @param {string} fileType
         * @param {string} fileName
         * @returns {boolean}
         */
        isVideo(fileType, fileName) {
            if (fileType?.startsWith('video/')) return true;
            if (!fileName) return false;
            const ext = fileName.split('.').pop()?.toLowerCase();
            return ['mp4', 'webm', 'ogg'].includes(ext);
        },

        isAudio(fileType, fileName) {
            if (fileType?.startsWith('audio/')) return true;
            if (!fileName) return false;
            const ext = fileName.split('.').pop()?.toLowerCase();
            return ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma', 'opus'].includes(ext);
        },

        /**
         * Определяет, было ли сообщение отправлено текущим пользователем.
         * @param {Object} msg
         * @returns {boolean}
         */
        isOwnMessage(msg) {
            const currentUser = localStorage.getItem('user_id');
            const sender = msg.json?.content?.sender || null;
            return currentUser && sender === currentUser;
        },

        /**
         * Форматирует метку времени сообщения для отображения.
         * @param {Object} msg
         * @returns {string|null}
         */
        formatTime(msg) {
            const ts = msg.received_ts || msg.json?.origin_server_ts || (typeof msg.json === 'string' ? JSON.parse(msg.json).origin_server_ts : null);
            if (!ts) {
                return null;
            }

            const date = new Date(ts);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },
        
        /**
         * Получает и отображает список участников комнаты для выбранной комнаты.
         * @param {string} room_id
         * @returns {Promise<void>}
         */
        async openMembers(room_id) {
            const token = localStorage.getItem('token');
            this.roomMembers = [];

            const res = await fetch('/api/v1/rooms/'+room_id+'/members', {
                method: 'GET',
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                }
            });

            const result = await res.json();
            console.log(result);

            if (result.error) {
                notify(result.error, 'warning', 5000);
                return;
            }
            
            this.roomMembers = result;
        },

        /**
         * Обрабатывает открытие модального окна приглашения и проверяет выбор комнаты.
         */
        openInvite() {
            if (!this.roomId) {
                notify('Выберите комнату перед приглашением', 'warning', 4000);
                return;
            }
        },

        /**
         * Принимает приглашение в текущую комнату через API.
         * @returns {Promise<void>}
         */
        async acceptInvite() {
            if (!this.roomId) {
                notify('Выберите комнату для принятия приглашения', 'warning', 4000);
                return;
            }

            const token = localStorage.getItem('token');

            const res = await fetch('/api/v1/rooms/'+ this.roomId +'/accept', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({})
            });

            const result = await res.json();

            if (result.error) {
                notify(result.error, 'warning', 5000);
                return;
            }

            notify('Вы приняли приглашение в комнату', 'success', 4000);
            this.roomMembership = 'join';
            this.joinedRooms();
        },
        
        /**
         * Отправляет приглашение пользователю в текущую комнату.
         * @param {SubmitEvent} e
         * @returns {Promise<void>}
         */
        async invite(e) {
            e.preventDefault();

            const form = e.target;
            const token = localStorage.getItem('token');

            const data = Object.fromEntries(new FormData(form).entries());

            const res = await fetch('/api/v1/rooms/'+ this.roomId +'/invite', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await res.json();

            if (result.error) {
                notify(result.error, 'warning', 5000);
                return;
            }

            form.reset();
        },

        /**
         * Банит пользователя в текущей комнате.
         * @param {string} userId
         * @returns {Promise<void>}
         */
        async ban(userId) {
            const token = localStorage.getItem('token');

            const res = await fetch('/api/v1/rooms/'+ this.roomId +'/ban', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ user_id: userId })
            });

            const result = await res.json();

            if (result.error) {
                notify(result.error, 'warning', 5000);
                return;
            }

            notify('Пользователь забанен', 'success', 4000);
            this.openMembers(this.roomId);
        },

        /**
         * Снимает бан с пользователя в текущей комнате.
         * @param {string} userId
         * @returns {Promise<void>}
         */
        async unban(userId) {
            const token = localStorage.getItem('token');

            const res = await fetch('/api/v1/rooms/'+ this.roomId +'/unban', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ user_id: userId })
            });

            const result = await res.json();

            if (result.error) {
                notify(result.error, 'warning', 5000);
                return;
            }

            notify('Бан снят', 'success', 4000);
            this.openMembers(this.roomId);
        },

        /**
         * Ищет публичные комнаты по запросу.
         * @returns {Promise<void>}
         */
        async searchPublicRooms() {
            const token = localStorage.getItem('token');
            this.publicSearchSearched = true;
            this.publicSearchLoading = true;

            const res = await fetch('/api/v1/publicRooms/?q=' + encodeURIComponent(this.publicSearchQuery), {
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                }
            });

            const result = await res.json();

            if (result.error) {
                notify(result.error, 'warning', 5000);
                this.publicSearchLoading = false;
                return;
            }

            this.publicRooms = result;
            this.publicSearchLoading = false;
        },

        /**
         * Присоединяется к публичной комнате.
         * @param {string} roomId
         * @returns {Promise<void>}
         */
        async joinPublicRoom(roomId) {
            const token = localStorage.getItem('token');

            const res = await fetch('/api/v1/joinRoom/', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ room_id: roomId })
            });

            const result = await res.json();

            if (result.error) {
                notify(result.error, 'warning', 5000);
                return;
            }

            notify('Вы вошли в комнату', 'success', 3000);
            this.joinedRooms();
            this.publicRooms = this.publicRooms.filter(r => r.room_id !== roomId);
        },

        /**
         * Открывает модальное окно настроек комнаты.
         * Заполняет поля текущими значениями.
         */
        openRoomSettings() {
            this.settingsName = this.roomName;
            this.settingsTopic = this.roomTopic;
            this.settingsAvatar = this.roomAvatar;
            this.settingsJoinRule = this.roomJoinRule;
            this.settingsAvatarFile = null;
        },

        /**
         * Обрабатывает выбор файла аватара.
         * @param {Event} e
         */
        onAvatarChange(e) {
            const file = e.target.files?.[0];
            if (!file) return;
            this.settingsAvatarFile = file;

            const reader = new FileReader();
            reader.onload = (ev) => {
                this.settingsAvatar = ev.target.result;
            };
            reader.readAsDataURL(file);
        },

        /**
         * Сохраняет настройки комнаты через API.
         * @returns {Promise<void>}
         */
        async saveRoomSettings() {
            if (!this.roomId) return;

            const token = localStorage.getItem('token');

            let avatarUrl = this.roomAvatar;

            if (this.settingsAvatarFile) {
                const formData = new FormData();
                formData.append('file', this.settingsAvatarFile);
                formData.append('room_id', this.roomId);

                const uploadRes = await fetch('/api/v1/rooms/' + this.roomId + '/upload_avatar', {
                    method: 'POST',
                    headers: {
                        "Authorization": "Bearer " + token
                    },
                    body: formData
                });

                const uploadResult = await uploadRes.json();
                if (uploadResult.error) {
                    notify(uploadResult.error, 'warning', 5000);
                    return;
                }

                avatarUrl = uploadResult.file_url;
            }

            const body = {
                room_id: this.roomId,
                name: this.settingsName,
                topic: this.settingsTopic,
                join_rule: this.settingsJoinRule,
                avatar_url: avatarUrl
            };

            const res = await fetch('/api/v1/rooms/' + this.roomId + '/update', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(body)
            });

            const result = await res.json();

            if (result.error) {
                notify(result.error, 'warning', 5000);
                return;
            }

            notify('Настройки сохранены', 'success', 3000);

            this.roomName = this.settingsName;
            this.roomTopic = this.settingsTopic;
            this.roomAvatar = avatarUrl;
            this.roomJoinRule = this.settingsJoinRule;

            const modalEl = document.getElementById('roomSettings');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            this.joinedRooms();
        },
    },

    mounted() {
        const token = localStorage.getItem('token');

        if (!token) {
            window.location.href = '/';
        }

        document.cookie = 'token=' + encodeURIComponent(token) + '; path=/; max-age=86400; SameSite=Lax';
        this.joinedRooms();
        this.parseHash();

        window.addEventListener('hashchange', this.parseHash);

        setInterval(() => this.joinedRooms(), 60000);
        setInterval(() => this.sync(), 1000);
        
        document.getElementById('formCreateRoom')
            .addEventListener('submit', this.createRoom);

        // перехват формы Vue-способом
        document.getElementById('sendMessage')
            .addEventListener('submit', this.sendMessage);
            
        const hash = window.location.hash;
        const roomId = hash.replace('#room_', '');
        
        if (roomId) {
            this.roomId = roomId;
            this.roomName = localStorage.getItem('room_name');
        }
        
        document.getElementById('formInvite')
            .addEventListener('submit', this.invite);

        const modalEl = document.getElementById('videoPreviewModal');
        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', () => {
                const video = modalEl.querySelector('video');
                if (video) {
                    video.pause();
                    video.currentTime = 0;
                }
            });
        }

        const publicRoomsModal = document.getElementById('publicRooms');
        if (publicRoomsModal) {
            publicRoomsModal.addEventListener('shown.bs.modal', () => {
                this.publicSearchQuery = '';
                this.publicRooms = [];
                this.publicSearchSearched = false;
                this.searchPublicRooms();
            });
        }
    }
});

app.mount('#app');
