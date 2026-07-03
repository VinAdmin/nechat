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
            messagesStore: {}, // кеш сообщений по room_id
            messages: [],
            roomId: null,
            roomName: '',
            roomMembership: null,
            syncToken: "",
            roomMembers: []
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
            
            const data = Object.fromEntries(new FormData(form).entries());

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
                this.roomMembership = currentRoom ? currentRoom.membership : this.roomMembership;

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

            const data = Object.fromEntries(new FormData(form).entries());

            data.msgtype = 'm.text';
            data.room_id = this.roomId;

            const res = await fetch('/api/v1/rooms/', {
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
        }
    },

    mounted() {
        const token = localStorage.getItem('token');

        if (!token) {
            window.location.href = '/';
        }

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
    }
});

app.mount('#app');
