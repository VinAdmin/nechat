# WEB API V1 Documentation

**Base URL:** `https://example.com/api/v1`

**Content-Type:** `application/json` (for file uploads use `multipart/form-data`)

**Auth:** All endpoints except `registration` and `authorization` require `Authorization: Bearer <token>` header.

---

## Authentication

### POST /api/v1/registration/

Register a new user.

**Request:**
```json
{
    "login": "username",
    "password": "secret"
}
```

**Response:**
```json
{
    "status": "ok"
}
```

### POST /api/v1/authorization/

Log in and get a token.

**Request:**
```json
{
    "login": "username",
    "password": "secret"
}
```

**Response:**
```json
{
    "status": "ok",
    "user_id": "@username:example.com",
    "token": "access_token_string"
}
```

---

## Rooms

### GET /api/v1/joined_rooms/

Returns a list of rooms the user has joined or been invited to.

**Response:**
```json
[
    {
        "room_id": "!uuid:example.com",
        "name": "Room Name",
        "creator": "@user:example.com",
        "membership": "join|invite",
        "cdate": "1234567890"
    }
]
```

### POST /api/v1/createRoom/

Create a new room.

**Request:**
```json
{
    "name": "Room Name"
}
```

**Response:**
```json
{
    "status": "ok"
}
```

### POST /api/v1/rooms/

Send a text message, upload a file, or upload a video to the current room. Also handles sub-routes (see below).

**Text message:**
```json
{
    "room_id": "!uuid:example.com",
    "msgtype": "m.text",
    "body": "Hello!"
}
```

**File upload (multipart/form-data):**

| Field | Description |
|-------|-------------|
| `room_id` | Room ID |
| `msgtype` | `m.file` |
| `file` | File blob (any type: images, documents, archives, etc.) |
| `upload_id` | (optional) For chunked uploads |
| `chunk_index` | (optional) Chunk number (1-based) |
| `chunk_count` | (optional) Total chunks |
| `file_name` | (optional) Original filename |
| `file_size` | (optional) File size |
| `body` | (optional) Text caption |

**Video upload (multipart/form-data):**

Same endpoint, same fields. Use `msgtype: m.file` and attach a video file. The server detects the MIME type automatically. Supported formats: MP4, WebM, AVI, MOV, MKV, etc.

| Field | Value |
|-------|-------|
| `room_id` | Room ID |
| `msgtype` | `m.file` |
| `file` | Video file blob |
| `body` | (optional) Video caption or filename |

**Note:** Files over 5 MB are split into chunks. Use `upload_id`, `chunk_count`, and `chunk_index` to reassemble them server-side. The server returns `{"status": "chunk_received", "chunk_index": N, "chunk_count": M}` after each intermediate chunk, and a final `{"status": "ok", "event_id": "$id"}` after the last chunk.

**Response:**
```json
{
    "status": "ok",
    "event_id": "$event_uuid"
}
```

**Video in sync response:**

When a video is uploaded, the sync endpoint returns `type: "m.file"` with `file_type` starting with `video/`:

```json
{
    "event_id": "$event_uuid",
    "json": {
        "type": "m.file",
        "content": {
            "body": "video.mp4",
            "file_url": "/default/uploads/1234567890_abcd1234_video.mp4",
            "file_name": "video.mp4",
            "file_type": "video/mp4",
            "file_size": 10485760
        }
    }
}
```

---

## Room sub-routes

Requests are routed by URI path: `/api/v1/rooms/{room_id}/{action}`

### GET /api/v1/rooms/{room_id}/members

List room members.

**Response:**
```json
[
    {
        "event_id": "$event_uuid",
        "user_id": "@user:example.com",
        "sender": "@creator:example.com",
        "room_id": "!uuid:example.com",
        "membership": "join|invite|ban"
    }
]
```

### POST /api/v1/rooms/{room_id}/invite

Invite a user to the room.

**Request:**
```json
{
    "user_id": "@username:example.com"
}
```

**Response:**
```json
{
    "status": "ok"
}
```

### POST /api/v1/rooms/{room_id}/accept

Accept a pending invitation.

**Request:** `{}` (empty)

**Response:**
```json
{
    "status": "ok"
}
```

### POST /api/v1/rooms/{room_id}/ban

Ban a user from the room.

**Request:**
```json
{
    "user_id": "@username:example.com"
}
```

**Response:**
```json
{
    "status": "ok"
}
```

### POST /api/v1/rooms/{room_id}/unban

Unban a user in the room.

**Request:**
```json
{
    "user_id": "@username:example.com"
}
```

**Response:**
```json
{
    "status": "ok"
}
```

---

## Sync

### GET /api/v1/sync/?since={timestamp}

Poll for new events (messages, invites).

**Query params:**
| Param | Description |
|-------|-------------|
| `since` | Timestamp of last sync (optional) |

**Response:**
```json
{
    "next_batch": 1234567890,
    "rooms": {
        "join": {
            "!uuid:example.com": {
                "events": [
                    {
                        "event_id": "$event_uuid",
                        "json": {
                            "event_id": "$event_uuid",
                            "type": "m.text|m.file",
                            "room_id": "!uuid:example.com",
                            "sender": "@user:example.com",
                            "origin_server_ts": 1234567890000,
                            "content": {
                                "body": "Hello!",
                                "room_id": "!uuid:example.com",
                                "sender": "@user:example.com",
                                "file_url": "/default/uploads/file.ext",
                                "file_name": "file.ext",
                                "file_type": "image/png|video/mp4|...",
                                "file_size": 12345
                            }
                        }
                    }
                ]
            }
        },
        "invite": {
            "!uuid:example.com": {
                "invite_state": {
                    "events": []
                }
            }
        }
    }
}
```

---

## Error Responses

All endpoints return errors in the following format:

```json
{
    "error": "Error description"
}
```

Common HTTP status codes:
- `200` — Success
- `400` — Bad request
- `401` — Unauthorized / Invalid token
- `500` — Server error
