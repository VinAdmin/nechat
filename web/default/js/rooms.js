 /**
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 **/
 
const app = Vue.createApp({
    data() {
        return {
            rooms: [],
            messagesStore: {}, // как твой room {}
            messages: [],
            roomId: null,
            roomName: '',
            syncToken: "",
            roomMembers: []
        }
    },

    methods: {

        async joinedRooms() {
            const token = localStorage.getItem('token');

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

            if (data.length === 0) {
                history.replaceState(null, null, window.location.pathname);
            }
        },

        openRoom(room) {
            this.roomId = room.room_id;
            this.roomName = room.name;

            localStorage.setItem('room_id', room.room_id);
            localStorage.setItem('room_name', room.name);

            window.location.hash = `#room_${room.room_id}`;

            this.updateMessages();
        },
        
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
        
        isAtBottom() {
            const el = this.$refs.messages;
            if (!el) return true;

            return el.scrollHeight - el.scrollTop - el.clientHeight < 50;
        },

        scrollToBottom(smooth = true) {
            const el = this.$refs.messages;
            if (!el) return;

            el.scrollTo({
                top: el.scrollHeight,
                behavior: smooth ? 'smooth' : 'auto'
            });
        },

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

        parseHash() {
            const hash = window.location.hash.substring(1);
            const params = new URLSearchParams(hash);

            const id = params.get('room');
            const name = params.get('name');

            if (id) {
                this.roomId = id;
                this.roomName = name || localStorage.getItem('room_name');

                this.updateMessages();
            }
        },

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
