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
            videoSendFile: null,
            videoSendPreview: '',
            videoSendCaption: '',
            videoSending: false,
            settingsName: '',
            settingsTopic: '',
            settingsAvatar: '',
            settingsJoinRule: 'public',
            settingsAvatarFile: null,
            publicRooms: [],
            publicSearchQuery: '',
            publicSearchSearched: false,
            publicSearchLoading: false,
            showRooms: false,
            profileUserId: '',
            profileAvatar: '',
            profileOldPassword: '',
            profilePassword: '',
            profileToken: '',
            profileAvatarFile: null,
            voiceRecording: false,
            voiceMediaRecorder: null,
            voiceChunks: [],
            voiceBlob: null,
            voiceTimer: null,
            voiceSeconds: 0,
            unreadCounts: {},
            prevRoomCounts: {},
            replyTo: null,
            roomCreator: null,
            syncFailed: false,
            pendingScroll: false,
            uploadProgress: null,
            pendingFile: null,
            onlineUsers: [],
            typingUsers: [],
            typingTimer: null,
            showEmojiPicker: false,
            searchQuery: '',
            searchResults: [],
            searchLoading: false,
            showSearch: false,
            editingMessage: null,
            editBody: '',
            emojiCategories: {
                'Смайлики': ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','😊','😇','🥰','😍','🤩','😘','😗','😚','😙','🥲','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','🫡','🤐','🤨','😐','😑','😶','🫥','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🥵','🥶','🥴','😵','🤯','🤠','🥳','🥸','😎','🤓','🧐'],
                'Жесты': ['👋','🤚','🖐️','✋','🖖','🫱','🫲','🫳','🫴','👌','🤌','🤏','✌️','🤞','🫰','🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','🫵','👍','👎','✊','👊','🤛','🤜','👏','🙌','🫶','👐','🤲','🤝','🙏'],
                'Животные': ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐻‍❄️','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🙈','🙉','🙊','🐒','🐔','🐧','🐦','🐤','🐣','🐥','🦆','🦅','🦉','🦇','🐺','🐗','🐴','🦄','🐝','🐛','🦋','🐌','🐞','🐜'],
                'Еда': ['🍎','🍐','🍊','🍋','🍌','🍉','🍇','🍓','🫐','🍈','🍒','🍑','🥭','🍍','🥥','🥝','🍅','🥑','🍆','🥔','🥕','🌽','🌶️','🫑','🥒','🥬','🥦','🧄','🧅','🍄','🥜','🌰','🍞','🥐','🥖','🫓','🥨','🥯','🥞','🧇'],
                'Объекты': ['⌚','📱','💻','⌨️','🖥️','🖨️','🖱️','🖲️','💾','💿','📀','📼','📷','📹','🎥','📽️','🎞️','📞','☎️','📟','📠','📺','📻','🎙️','🎚️','🎛️','🧭','⏱️','⏲️','⏰','🕰️','📡','🔋','🔌','💡','🔦','🕯️','🪔','🧯'],
                'Символы': ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❤️‍🔥','❤️‍🩹','❣️','💕','💞','💓','💗','💖','💘','💝','💟','☮️','✝️','☪️','🕉️','☸️','✡️','🔯','🕎','☯️','☦️','🛐','⛎','♈','♉','♊','♋','♌','♍']
            },
            dmUserId: ''
        }
    },

    methods: {

        saveCache() {
            try {
                localStorage.setItem('messagesStore', JSON.stringify(this.messagesStore));
                localStorage.setItem('unreadCounts', JSON.stringify(this.unreadCounts));
            } catch (e) {
                // ignore quota errors
            }
        },

        loadCache() {
            try {
                const ms = localStorage.getItem('messagesStore');
                if (ms) {
                    this.messagesStore = JSON.parse(ms);
                    const st = sessionStorage.getItem("sync");
                    if (st && Object.keys(this.messagesStore).length > 0) {
                        this.syncToken = st;
                    }
                }
                const uc = localStorage.getItem('unreadCounts');
                if (uc) this.unreadCounts = JSON.parse(uc);
            } catch (e) {
                // ignore parse errors
            }
        },

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
                if (currentRoom) {
                    this.roomName = currentRoom.name || '';
                    this.roomTopic = currentRoom.topic || '';
                    this.roomAvatar = currentRoom.avatar_url || '';
                    this.roomJoinRule = currentRoom.join_rule || 'public';
                    this.roomMembership = currentRoom.membership;
                    this.roomCreator = currentRoom.creator || null;
                }
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
            this.roomCreator = room.creator || null;

            localStorage.setItem('room_id', room.room_id);
            localStorage.setItem('room_name', room.name);

            window.location.hash = `#room_${room.room_id}`;

            this.unreadCounts[room.room_id] = 0;

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

            el.scrollTop = el.scrollHeight;
            if (smooth) {
                el.scrollTo({ top: el.scrollHeight, behavior: 'smooth' });
            }
        },

        scrollToMessage(eventId) {
            if (!eventId) return;
            const all = document.querySelectorAll('[data-event-id]');
            let target = null;
            for (const el of all) {
                if (el.getAttribute('data-event-id') === eventId) {
                    target = el;
                    break;
                }
            }
            if (!target) {
                const exists = this.messagesStore[this.roomId]?.some(e => e.event_id === eventId);
                notify(exists ? 'Сообщение за пределами видимости' : 'Сообщение не найдено', 'info', 2000);
                return;
            }
            try {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (e) {
                target.scrollIntoView(true);
            }
            const bubble = target.querySelector('.msg-bubble');
            if (bubble) {
                bubble.classList.add('msg-highlight');
                setTimeout(() => bubble.classList.remove('msg-highlight'), 2000);
            }
        },

        /**
         * Обновляет отображаемый массив сообщений из кеша для активной комнаты.
         */
        updateMessages() {
            const shouldScroll = this.pendingScroll || this.isAtBottom();
            this.pendingScroll = false;
            
            if (!this.messagesStore[this.roomId]) {
                this.messages = [];
                return;
            }
            
            this.messages = this.messagesStore[this.roomId].filter(m =>
                m.json?.content?.body || m.json?.content?.file_url || m.type === 'm.room.member'
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

            let res;
            try {
                res = await fetch('/api/v1/sync/?since=' + this.syncToken, {
                    headers: {
                        "Authorization": "Bearer " + token,
                        'Content-Type': 'application/json'
                    }
                });
            } catch (e) {
                if (!this.syncFailed) {
                    this.syncFailed = true;
                    notify('Нет соединения с сервером', 'warning', 3000);
                }
                return;
            }

            if (!res.ok) {
                if (!this.syncFailed) {
                    this.syncFailed = true;
                    notify('Ошибка сервера: ' + res.status, 'warning', 5000);
                }
                return;
            }

            const data = await res.json();
            
            if (data.error) {
                notify(data.error, 'warning', 5000);
                localStorage.clear();
                sessionStorage.clear();
                window.location.href = '/';
                return;
            }

            if (this.syncFailed) {
                this.syncFailed = false;
                notify('Соединение восстановлено', 'success', 3000);
            }
            
            const rooms = data.rooms?.join || {};
            
            for(const roomId in rooms){
                const events = rooms[roomId].events || {};

                if (!this.messagesStore[roomId]) {
                    this.messagesStore[roomId] = [];
                }

                let newEvents = 0;
                for(const event of events){
                    if (!event?.event_id) continue;

                    if (event.type === 'm.room.redaction') {
                        const redacts = event.json?.content?.redacts;
                        if (redacts) {
                            const target = this.messagesStore[roomId]?.find(m => m.event_id === redacts);
                            if (target) {
                                target.json.content.deleted = true;
                            }
                        }
                    }

                    const exists = this.messagesStore[roomId].some(e => e.event_id === event.event_id);
                    if (!exists) {
                        this.messagesStore[roomId].push(event);
                        newEvents++;
                    }
                }

                if (newEvents > 0) {
                    if (roomId !== this.roomId) {
                        this.unreadCounts[roomId] = (this.unreadCounts[roomId] || 0) + newEvents;
                        const lastEvent = events[events.length - 1];
                        const room = this.rooms.find(r => r.room_id === roomId);
                        const sender = lastEvent?.json?.content?.sender || '';
                        const body = lastEvent?.json?.content?.body || '';
                        const roomName = room?.name || roomId;
                        this.notifyBrowser(roomName, sender, body);
                        if (sender && sender !== localStorage.getItem('user_id')) {
                            this.playNotificationSound();
                            if (body) {
                                notify(roomName + ': ' + sender, 'info', 4000);
                            }
                        }
                    }
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

            this.saveCache();
            this.updateMessages();
            this.checkVersion();
        },

        async checkVersion() {
            try {
                const res = await fetch('/api/v1/version/', { cache: 'no-store' });
                if (!res.ok) return;
                const data = await res.json();
                if (!data.hash) return;
                if (!window.APP_HASH) {
                    window.APP_HASH = data.hash;
                } else if (data.hash !== window.APP_HASH) {
                    location.reload();
                }
            } catch (e) {
                // ignore
            }
        },

        playNotificationSound() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                if (ctx.state === 'suspended') {
                    ctx.resume();
                }

                const notes = [523.25, 659.25, 783.99];
                const noteLen = 0.08;
                const startTime = ctx.currentTime + 0.02;

                for (let i = 0; i < notes.length; i++) {
                    const g = ctx.createGain();
                    g.connect(ctx.destination);
                    g.gain.setValueAtTime(0, startTime + i * noteLen);
                    g.gain.linearRampToValueAtTime(0.25, startTime + i * noteLen + 0.01);
                    g.gain.exponentialRampToValueAtTime(0.001, startTime + i * noteLen + 0.2);

                    const o = ctx.createOscillator();
                    o.connect(g);
                    o.type = 'triangle';
                    o.frequency.value = notes[i];
                    o.start(startTime + i * noteLen);
                    o.stop(startTime + i * noteLen + 0.2);

                    const g2 = ctx.createGain();
                    g2.connect(ctx.destination);
                    g2.gain.setValueAtTime(0, startTime + i * noteLen);
                    g2.gain.linearRampToValueAtTime(0.08, startTime + i * noteLen + 0.01);
                    g2.gain.exponentialRampToValueAtTime(0.001, startTime + i * noteLen + 0.15);

                    const o2 = ctx.createOscillator();
                    o2.connect(g2);
                    o2.type = 'sine';
                    o2.frequency.value = notes[i] * 2;
                    o2.start(startTime + i * noteLen);
                    o2.stop(startTime + i * noteLen + 0.15);
                }
            } catch (e) {
                // audio not available
            }
        },

        notifyBrowser(roomName, sender, body) {
            if (document.hidden && window.Notification && Notification.permission === 'granted') {
                new Notification(roomName, {
                    body: sender + ': ' + body,
                    icon: '/default/favicon.ico'
                });
            } else if (window.Notification && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        },

        /**
         * Анализирует текущий хеш локации и восстанавливает состояние выбранной комнаты.
         */
        parseHash() {
            const hash = window.location.hash.substring(1);
            let id = null;
            let name = null;

            if (hash.startsWith('room_')) {
                id = hash.replace('room_', '');
                name = localStorage.getItem('room_name');
            } else {
                const params = new URLSearchParams(hash);
                id = params.get('room');
                name = params.get('name');
            }

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
                    this.roomCreator = currentRoom.creator || null;
                }

                this.updateMessages();
            }
        },

        /**
         * Отправляет текстовое сообщение в текущую открытую комнату.
         * @param {SubmitEvent} e
         * @returns {Promise<void>}
         */
        onBodyKeydown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const form = e.target.closest('form');
                if (form) form.requestSubmit();
            }
        },

        resizeTextarea(e) {
            const el = e.target;
            el.style.height = 'auto';
            el.style.height = el.scrollHeight + 'px';
        },

        uploadWithProgress(formData) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', '/api/v1/rooms/');
                xhr.setRequestHeader('Authorization', 'Bearer ' + localStorage.getItem('token'));

                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        this.uploadProgress = Math.round((e.loaded / e.total) * 100);
                    }
                };

                xhr.onload = () => {
                    this.uploadProgress = null;
                    try {
                        resolve(JSON.parse(xhr.responseText));
                    } catch {
                        reject(new Error('Invalid JSON'));
                    }
                };

                xhr.onerror = () => {
                    this.uploadProgress = null;
                    reject(new Error('Network error'));
                };

                xhr.send(formData);
            });
        },

        sendMessage(e) {
            e.preventDefault();

            const form = e.target;
            const token = localStorage.getItem('token');
            const bodyText = form.querySelector('textarea[name="body"], input[name="body"]')?.value || '';
            const replyTo = this.replyTo?.event_id || null;

            if (!bodyText.trim()) {
                notify('Введите сообщение', 'warning', 4000);
                return;
            }

            this.replyTo = null;

            const formData = new FormData();
            formData.set('room_id', this.roomId);
            formData.set('msgtype', 'm.text');
            formData.set('body', bodyText);
            if (replyTo) formData.set('reply_to', replyTo);

            this.resetForm(form);

            fetch('/api/v1/rooms/', {
                method: 'POST',
                headers: { "Authorization": "Bearer " + token },
                body: formData
            }).then(res => res.json()).then(result => {
                if (result.error) {
                    notify(result.error, 'warning', 5000);
                }
                this.pendingScroll = true;
            }).catch(() => {});
        },

        sendPendingFile() {
            const file = this.pendingFile?.file;
            if (!file) return;

            const token = localStorage.getItem('token');
            const bodyText = this.$refs.bodyInput?.value || '';
            const replyTo = this.replyTo?.event_id || null;
            this.replyTo = null;

            if (file.size > 1 * 1024 * 1024) {
                const opts = { token, file, bodyText, roomId: this.roomId, replyTo };
                this.uploadFileInChunks(opts).then(() => {
                    this.cancelPendingFile();
                    this.pendingScroll = true;
                }).catch(() => {});
            } else {
                const formData = new FormData();
                formData.append('room_id', this.roomId);
                formData.append('msgtype', 'm.file');
                formData.append('file', file, file.name);
                if (bodyText) formData.append('body', bodyText);
                if (replyTo) formData.append('reply_to', replyTo);

                this.uploadWithProgress(formData).then(() => {
                    this.cancelPendingFile();
                    this.pendingScroll = true;
                }).catch(() => {});
            }
        },

        resetForm(form) {
            this.replyTo = null;
            this.fileName = '';
            const bodyEl = form.querySelector('textarea[name="body"]');
            if (bodyEl) {
                bodyEl.value = '';
                bodyEl.style.height = 'auto';
            }
        },

        async uploadFileInChunks({token, file, bodyText, roomId, replyTo}) {
            const chunkSize = 1 * 1024 * 1024; // 1 MB
            const chunkCount = Math.ceil(file.size / chunkSize);
            const uploadId = `${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;

            try {
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
                    if (replyTo) {
                        formData.append('reply_to', replyTo);
                    }
                    if (index === chunkCount && bodyText) {
                        formData.append('body', bodyText);
                    }
                    formData.append('file', chunk, file.name);

                    this.uploadProgress = Math.round((index / chunkCount) * 100);

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
            } finally {
                this.uploadProgress = null;
            }
        },

        /**
         * Sets pendingFile for preview panel.
         * @param {Event} e
         */
        onFileChange(e) {
            const file = e.target.files?.[0];
            if (!file) return;
            e.target.value = '';
            this.setPendingFile(file);
        },

        /**
         * Attaches a file object to pendingFile for preview.
         * @param {File} file
         */
        setPendingFile(file) {
            if (this.pendingFile?.preview) URL.revokeObjectURL(this.pendingFile.preview);
            let type = 'file';
            if (file.type.startsWith('image/')) type = 'image';
            else if (file.type.startsWith('video/')) type = 'video';
            else if (file.type.startsWith('audio/')) type = 'audio';
            this.pendingFile = {
                file,
                preview: (type === 'image' || type === 'video') ? URL.createObjectURL(file) : null,
                type
            };
        },

        /**
         * Clears pendingFile.
         */
        cancelPendingFile() {
            if (this.pendingFile?.preview) URL.revokeObjectURL(this.pendingFile.preview);
            this.pendingFile = null;
        },

        /**
         * Formats bytes to human-readable string.
         * @param {number} bytes
         * @returns {string}
         */
        formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' Б';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' КБ';
            return (bytes / (1024 * 1024)).toFixed(1) + ' МБ';
        },

        /**
         * Обрабатывает выбор видеофайла и отправляет его в чат.
         * @param {Event} e
         * @returns {Promise<void>}
         */
        onVideoChange(e) {
            const file = e.target.files?.[0];
            if (!file) return;
            e.target.value = '';
            this.videoSendFile = file;
            this.videoSendPreview = URL.createObjectURL(file);
            this.videoSendCaption = '';
            this.videoSending = false;
            const modal = new bootstrap.Modal(document.getElementById('videoSendModal'));
            modal.show();
        },

        sendVideoFromModal() {
            if (!this.videoSendFile || this.videoSending) return;
            this.videoSending = true;
            const token = localStorage.getItem('token');
            const file = this.videoSendFile;
            const bodyText = this.videoSendCaption;
            const replyTo = this.replyTo?.event_id || null;
            this.replyTo = null;

            const doUpload = () => {
                if (file.size > 1 * 1024 * 1024) {
                    const opts = { token, file, bodyText, roomId: this.roomId, replyTo };
                    return this.uploadFileInChunks(opts);
                } else {
                    const formData = new FormData();
                    formData.append('room_id', this.roomId);
                    formData.append('msgtype', 'm.file');
                    formData.append('file', file, file.name);
                    if (bodyText) formData.append('body', bodyText);
                    if (replyTo) formData.append('reply_to', replyTo);
                    return this.uploadWithProgress(formData);
                }
            };

            doUpload().then(() => {
                this.closeVideoSendModal();
                this.pendingScroll = true;
            }).catch(() => {
                this.videoSending = false;
            });
        },

        closeVideoSendModal() {
            if (this.videoSendPreview) URL.revokeObjectURL(this.videoSendPreview);
            this.videoSendFile = null;
            this.videoSendPreview = '';
            this.videoSendCaption = '';
            this.videoSending = false;
            const modal = bootstrap.Modal.getInstance(document.getElementById('videoSendModal'));
            if (modal) modal.hide();
        },

        onAudioChange(e) {
            const file = e.target.files?.[0];
            if (!file) return;
            e.target.value = '';
            this.setPendingFile(file);
        },

        async startVoice() {
            if (this.voiceRecording) return;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                    ? 'audio/webm;codecs=opus'
                    : 'audio/webm';
                this.voiceMediaRecorder = new MediaRecorder(stream, { mimeType });
                this.voiceChunks = [];
                this.voiceBlob = null;
                this.voiceSeconds = 0;

                this.voiceMediaRecorder.ondataavailable = (e) => {
                    if (e.data.size > 0) this.voiceChunks.push(e.data);
                };

                this.voiceMediaRecorder.onstop = () => {
                    stream.getTracks().forEach(t => t.stop());
                    this.voiceBlob = new Blob(this.voiceChunks, { type: mimeType });
                    clearInterval(this.voiceTimer);
                    this.voiceTimer = null;
                };

                this.voiceMediaRecorder.start();
                this.voiceRecording = true;
                this.voiceTimer = setInterval(() => { this.voiceSeconds++; }, 1000);
            } catch (err) {
                notify('Микрофон недоступен', 'warning', 3000);
            }
        },

        stopVoice() {
            if (!this.voiceRecording || !this.voiceMediaRecorder) return;
            this.voiceMediaRecorder.stop();
            this.voiceRecording = false;
        },

        cancelVoice() {
            if (this.voiceMediaRecorder && this.voiceMediaRecorder.state !== 'inactive') {
                this.voiceMediaRecorder.onstop = null;
                this.voiceMediaRecorder.stop();
                this.voiceMediaRecorder.stream?.getTracks().forEach(t => t.stop());
            }
            clearInterval(this.voiceTimer);
            this.voiceTimer = null;
            this.voiceRecording = false;
            this.voiceBlob = null;
            this.voiceChunks = [];
            this.voiceSeconds = 0;
        },

        async sendVoice() {
            if (!this.voiceBlob) return;
            const token = localStorage.getItem('token');
            const formData = new FormData();
            formData.append('room_id', this.roomId);
            formData.append('msgtype', 'm.file');
            formData.append('file', this.voiceBlob, 'voice_' + Date.now() + '.webm');
            if (this.replyTo) formData.append('reply_to', this.replyTo.event_id);

            const res = await fetch('/api/v1/rooms/', {
                method: 'POST',
                headers: { "Authorization": "Bearer " + token },
                body: formData
            });

            const result = await res.json();
            if (result.error) {
                notify(result.error, 'warning', 5000);
            }

            this.voiceBlob = null;
            this.voiceChunks = [];
            this.voiceSeconds = 0;
            this.replyTo = null;
            this.pendingScroll = true;
        },

        formatVoiceTime(s) {
            const m = Math.floor(s / 60);
            const sec = s % 60;
            return m + ':' + (sec < 10 ? '0' : '') + sec;
        },

        setReply(msg) {
            this.replyTo = {
                event_id: msg.event_id,
                sender: msg.json?.content?.sender || '',
                body: msg.json?.content?.body || msg.json?.content?.file_name || ''
            };
        },

        cancelReply() {
            this.replyTo = null;
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
            return ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma', 'opus', 'webm'].includes(ext);
        },

        /**
         * Определяет, было ли сообщение отправлено текущим пользователем.
         * @param {Object} msg
         * @returns {boolean}
         */
        isRoomOwner() {
            const uid = localStorage.getItem('user_id');
            if (this.roomCreator === uid) return true;
            const room = this.rooms.find(r => r.room_id === this.roomId);
            return room?.creator === uid;
        },

        isOwnMessage(msg) {
            const currentUser = localStorage.getItem('user_id');
            const sender = msg.json?.content?.sender || null;
            return currentUser && sender === currentUser;
        },

        isSameSender(index) {
            if (index <= 0) return false;
            const prev = this.messages[index - 1];
            const curr = this.messages[index];
            if (!prev || !curr) return false;
            if (prev.type === 'm.room.member' || curr.type === 'm.room.member') return false;
            const prevSender = prev.json?.content?.sender;
            const currSender = curr.json?.content?.sender;
            if (!prevSender || !currSender) return false;
            if (prevSender !== currSender) return false;
            const prevTs = this.msgTs(prev);
            const currTs = this.msgTs(curr);
            if (!prevTs || !currTs) return false;
            return (currTs - prevTs) < 300000;
        },

        /**
         * Форматирует метку времени сообщения для отображения.
         * @param {Object} msg
         * @returns {string|null}
         */
        msgTs(msg) {
            const ts = msg.received_ts || msg.json?.origin_server_ts || (typeof msg.json === 'string' ? JSON.parse(msg.json).origin_server_ts : null);
            if (!ts) return null;
            return ts < 1e12 ? ts * 1000 : ts;
        },

        msgDate(msg) {
            const ts = this.msgTs(msg);
            if (!ts) return null;
            const d = new Date(ts);
            return d.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' });
        },

        showDateSeparator(msg, index) {
            if (index === 0) return true;
            const prev = this.messages[index - 1];
            const prevTs = this.msgTs(prev);
            const currTs = this.msgTs(msg);
            if (!prevTs || !currTs) return false;
            const prevDate = new Date(prevTs);
            const currDate = new Date(currTs);
            return prevDate.toDateString() !== currDate.toDateString();
        },

        formatTime(msg) {
            const ts = this.msgTs(msg);
            if (!ts) return null;

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

        async declineInvite() {
            if (!this.roomId) {
                return;
            }

            const token = localStorage.getItem('token');

            const res = await fetch('/api/v1/rooms/'+ this.roomId +'/leave', {
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

            if (result.event && this.messagesStore[this.roomId]) {
                this.messagesStore[this.roomId].push(result.event);
                this.updateMessages();
            }

            const leftRoomId = this.roomId;
            notify('Вы отказались от приглашения', 'success', 4000);
            this.rooms = this.rooms.filter(r => r.room_id !== leftRoomId);
            this.roomId = null;
            this.roomMembership = null;
            this.messages = [];
            this.joinedRooms();
        },

        async leaveRoom() {
            if (!this.roomId) {
                return;
            }

            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('leaveConfirmModal'));
            modal.show();
        },

        async confirmLeave() {
            if (!this.roomId) {
                return;
            }

            const token = localStorage.getItem('token');

            const res = await fetch('/api/v1/rooms/'+ this.roomId +'/leave', {
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

            notify('Вы вышли из комнаты', 'success', 4000);
            this.rooms = this.rooms.filter(r => r.room_id !== this.roomId);
            this.roomId = null;
            this.roomMembership = null;
            this.messages = [];
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
            const currentUser = localStorage.getItem('user_id');

            if (this.roomCreator !== currentUser) {
                notify('Только владелец комнаты может банить пользователей', 'warning', 5000);
                return;
            }

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
        async deleteMessage(eventId) {
            const token = localStorage.getItem('token');

            const res = await fetch('/api/v1/rooms/'+ this.roomId +'/delete', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ event_id: eventId })
            });

            const result = await res.json();

            if (result.error) {
                notify(result.error, 'warning', 5000);
                return;
            }

            const msg = this.messagesStore[this.roomId]?.find(m => m.event_id === eventId);
            if (msg) {
                msg.json.content.deleted = true;
            }

            this.updateMessages();
        },

        async unban(userId) {
            const token = localStorage.getItem('token');
            const currentUser = localStorage.getItem('user_id');

            if (this.roomCreator !== currentUser) {
                notify('Только владелец комнаты может снимать бан', 'warning', 5000);
                return;
            }

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
         * Выгоняет пользователя из комнаты (только владелец).
         * В отличие от бана, пользователь сможет повторно войти в публичную комнату.
         * @param {string} userId
         * @returns {Promise<void>}
         */
        async kick(userId) {
            const token = localStorage.getItem('token');
            const currentUser = localStorage.getItem('user_id');

            if (this.roomCreator !== currentUser) {
                notify('Только владелец комнаты может выгонять пользователей', 'warning', 5000);
                return;
            }

            const res = await fetch('/api/v1/rooms/'+ this.roomId +'/kick', {
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

            notify('Пользователь выгнан из комнаты', 'success', 4000);
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
         * Открывает модальное окно профиля и загружает данные.
         */
        async openProfile() {
            const token = localStorage.getItem('token');
            this.profileToken = token;
            this.profilePassword = '';
            this.profileAvatarFile = null;

            const res = await fetch('/api/v1/profile/', {
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                }
            });

            const result = await res.json();
            if (!result.error) {
                this.profileUserId = result.user_id || localStorage.getItem('user_id');
                this.profileAvatar = result.avatar_url || '';
            } else {
                this.profileUserId = localStorage.getItem('user_id');
            }
        },

        /**
         * Обрабатывает выбор файла аватара профиля.
         */
        onProfileAvatarChange(e) {
            const file = e.target.files?.[0];
            if (!file) return;
            this.profileAvatarFile = file;
            const reader = new FileReader();
            reader.onload = (ev) => { this.profileAvatar = ev.target.result; };
            reader.readAsDataURL(file);
        },

        /**
         * Копирует токен в буфер обмена.
         */
        copyToken() {
            const field = document.getElementById('tokenField');
            if (!field) return;
            field.select();
            navigator.clipboard?.writeText(field.value);
            notify('Токен скопирован', 'success', 2000);
        },

        /**
         * Сохраняет профиль: аватар и/или пароль.
         */
        async saveProfile() {
            const token = localStorage.getItem('token');

            if (this.profilePassword && !this.profileOldPassword) {
                notify('Введите старый пароль', 'warning', 3000);
                return;
            }

            if (this.profilePassword) {
                const res = await fetch('/api/v1/profile/', {
                    method: 'POST',
                    headers: {
                        "Authorization": "Bearer " + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        old_password: this.profileOldPassword,
                        new_password: this.profilePassword
                    })
                });

                const result = await res.json();
                if (result.error) {
                    notify(result.error, 'warning', 5000);
                    return;
                }

                notify('Пароль изменён', 'success', 3000);
                this.profileOldPassword = '';
                this.profilePassword = '';
            }

            if (this.profileAvatarFile) {
                const formData = new FormData();
                formData.append('avatar', this.profileAvatarFile);

                const res = await fetch('/api/v1/profile/', {
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

                notify('Аватар сохранён', 'success', 3000);
                this.profileAvatarFile = null;

                if (this.profileAvatar && this.profileAvatar.startsWith('/f/')) {
                    this.profileAvatar = this.profileAvatar + '?t=' + Date.now();
                }
            }

            if (!this.profilePassword && !this.profileAvatarFile) {
                notify('Нет изменений для сохранения', 'info', 3000);
            }
        },

        /**
         * Завершает сессию: удаляет токен на сервере и очищает localStorage.
         * @returns {Promise<void>}
         */
        async logout() {
            const token = localStorage.getItem('token');

            const res = await fetch('/api/v1/logout/', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                }
            });

            localStorage.clear();
            document.cookie = 'token=; path=/; max-age=0; SameSite=Lax';
            window.location.href = '/';
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

        // ===== Presence =====
        async heartbeat() {
            const token = localStorage.getItem('token');
            if (!token) return;

            try {
                const res = await fetch('/api/v1/presence/', {
                    method: 'POST',
                    headers: {
                        "Authorization": "Bearer " + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({})
                });
                const data = await res.json();
                if (data.online) {
                    this.onlineUsers = data.online;
                }
            } catch (e) {}
        },

        isOnline(userId) {
            return this.onlineUsers.includes(userId);
        },

        // ===== Typing =====
        async sendTyping() {
            const token = localStorage.getItem('token');
            if (!token || !this.roomId) return;

            try {
                await fetch('/api/v1/typing/', {
                    method: 'POST',
                    headers: {
                        "Authorization": "Bearer " + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ room_id: this.roomId })
                });
            } catch (e) {}
        },

        async stopTyping() {
            const token = localStorage.getItem('token');
            if (!token || !this.roomId) return;

            try {
                await fetch('/api/v1/typing/', {
                    method: 'DELETE',
                    headers: {
                        "Authorization": "Bearer " + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ room_id: this.roomId })
                });
            } catch (e) {}
        },

        async checkTyping() {
            const token = localStorage.getItem('token');
            if (!token || !this.roomId) return;

            try {
                const res = await fetch('/api/v1/getTyping/?room_id=' + encodeURIComponent(this.roomId), {
                    headers: {
                        "Authorization": "Bearer " + token,
                        'Content-Type': 'application/json'
                    }
                });
                const data = await res.json();
                this.typingUsers = data.typing || [];
            } catch (e) {}
        },

        onTypingInput() {
            this.sendTyping();
            clearTimeout(this.typingTimer);
            this.typingTimer = setTimeout(() => this.stopTyping(), 3000);
        },

        // ===== Emoji =====
        toggleEmoji() {
            this.showEmojiPicker = !this.showEmojiPicker;
        },

        insertEmoji(emoji) {
            const el = this.$refs.bodyInput;
            if (!el) return;

            const start = el.selectionStart;
            const end = el.selectionEnd;
            const text = el.value;
            el.value = text.slice(0, start) + emoji + text.slice(end);
            el.selectionStart = el.selectionEnd = start + emoji.length;
            el.focus();

            this.showEmojiPicker = false;
        },

        // ===== Search =====
        toggleSearch() {
            this.showSearch = !this.showSearch;
            if (!this.showSearch) {
                this.searchQuery = '';
                this.searchResults = [];
            }
        },

        async searchMessages() {
            const token = localStorage.getItem('token');
            if (!token || !this.roomId || !this.searchQuery.trim()) return;

            this.searchLoading = true;
            try {
                const res = await fetch('/api/v1/search/?room_id=' + encodeURIComponent(this.roomId) + '&q=' + encodeURIComponent(this.searchQuery), {
                    headers: {
                        "Authorization": "Bearer " + token,
                        'Content-Type': 'application/json'
                    }
                });
                this.searchResults = await res.json();
            } catch (e) {
                this.searchResults = [];
            }
            this.searchLoading = false;
        },

        // ===== Edit =====
        startEdit(msg) {
            this.editingMessage = msg;
            this.editBody = msg.json?.content?.body || '';
            this.$nextTick(() => {
                const el = document.getElementById('editInput');
                if (el) {
                    el.focus();
                    el.style.height = 'auto';
                    el.style.height = el.scrollHeight + 'px';
                }
            });
        },

        cancelEdit() {
            this.editingMessage = null;
            this.editBody = '';
        },

        async saveEdit() {
            if (!this.editingMessage || !this.editBody.trim()) return;

            const token = localStorage.getItem('token');
            const res = await fetch('/api/v1/editMessage/', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    event_id: this.editingMessage.event_id,
                    room_id: this.roomId,
                    body: this.editBody.trim()
                })
            });

            const result = await res.json();
            if (result.error) {
                notify(result.error, 'warning', 5000);
                return;
            }

            const msg = this.messagesStore[this.roomId]?.find(m => m.event_id === this.editingMessage.event_id);
            if (msg) {
                msg.json.content.body = this.editBody.trim();
                msg.json.content.edited = true;
            }

            this.updateMessages();
            this.cancelEdit();
        },

        onEditKeydown(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.saveEdit();
            }
            if (e.key === 'Escape') {
                this.cancelEdit();
            }
        },

        // ===== DM =====
        async startDM() {
            if (!this.dmUserId.trim()) return;

            const token = localStorage.getItem('token');
            const targetId = this.dmUserId.trim();

            const res = await fetch('/api/v1/directMessage/', {
                method: 'POST',
                headers: {
                    "Authorization": "Bearer " + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ user_id: targetId })
            });

            const result = await res.json();
            if (result.error) {
                notify(result.error, 'warning', 5000);
                return;
            }

            const modalEl = document.getElementById('dmModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            this.dmUserId = '';

            this.joinedRooms();
            setTimeout(() => {
                const room = this.rooms.find(r => r.room_id === result.room_id);
                if (room) this.openRoom(room);
            }, 500);
        },

        async openDM(userId) {
            const token = localStorage.getItem('token');

            const res = await fetch('/api/v1/directMessage/', {
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

            this.joinedRooms();
            setTimeout(() => {
                const room = this.rooms.find(r => r.room_id === result.room_id);
                if (room) this.openRoom(room);
            }, 500);
        }
    },

    mounted() {
        const token = localStorage.getItem('token');

        if (!token) {
            window.location.href = '/';
        }

        if (window.Notification && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        document.cookie = 'token=' + encodeURIComponent(token) + '; path=/; max-age=86400; SameSite=Lax';
        this.loadCache();
        this.joinedRooms();
        this.parseHash();
        this.sync();

        window.addEventListener('hashchange', this.parseHash);

        setInterval(() => this.joinedRooms(), 30000);
        setInterval(() => this.sync(), 1000);
        setInterval(() => this.heartbeat(), 15000);
        setInterval(() => { if (this.roomId) this.checkTyping(); }, 3000);
        this.heartbeat();
        
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
            this.updateMessages();
        }
        
        document.getElementById('formInvite')
            .addEventListener('submit', this.invite);

        const videoViewerModal = document.getElementById('videoPreviewModal');
        if (videoViewerModal) {
            videoViewerModal.addEventListener('hidden.bs.modal', () => {
                const video = videoViewerModal.querySelector('video');
                if (video) {
                    video.pause();
                    video.currentTime = 0;
                }
            });
        }

        const modalEl = document.getElementById('videoSendModal');
        if (modalEl) {
            modalEl.addEventListener('hidden.bs.modal', () => {
                this.closeVideoSendModal();
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
