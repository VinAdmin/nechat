# Документация Web API

## Общая информация

Проект предоставляет простой JSON API по пути `/api/v1/`.
API поддерживает регистрацию, авторизацию, создание комнат, синхронизацию событий, управление участниками и отправку сообщений.

Все запросы, которые изменяют или читают пользовательские данные, требуют заголовок `Authorization` с bearer-токеном.

---

## Общие заголовки

- `Content-Type: application/json`
- `Authorization: Bearer <token>`

> Токен возвращается при вызове `/api/v1/authorization/` и обязателен для защищённых запросов.

---

## Аутентификация

### Регистрация пользователя

- URL: `/api/v1/registration/`
- Метод: `POST`
- Тело:
  - `login` (string) — логин пользователя
  - `password` (string) — пароль пользователя

Пример тела запроса:

```json
{
  "login": "alice",
  "password": "secret123"
}
```

Успешный ответ:

```json
{
  "status": "ok"
}
```

Пример ошибки:

```json
{
  "error": "A user with this name already exists."
}
```

---

### Авторизация и получение токена

- URL: `/api/v1/authorization/`
- Метод: `POST`
- Тело:
  - `login` (string)
  - `password` (string)

Пример тела запроса:

```json
{
  "login": "alice",
  "password": "secret123"
}
```

Успешный ответ:

```json
{
  "status": "ok",
  "user_id": "@alice:example.com",
  "token": "<jwt-token>"
}
```

Примеры ошибок:

```json
{
  "error": "Incorrect login or password"
}
```

```json
{
  "error": "Unable to obtain a token"
}
```

---

## Защищённые эндпойнты

Для всех защищённых запросов требуется заголовок `Authorization`:

```http
Authorization: Bearer <token>
```

Токен действует примерно один час.

---

## Создание комнаты

- URL: `/api/v1/createRoom/`
- Метод: `POST`
- Тело:
  - `name` (string) — название комнаты

Пример тела запроса:

```json
{
  "name": "Моя комната"
}
```

Успешный ответ:

```json
{
  "status": "ok"
}
```

Ошибка:

```json
{
  "error": "Something was wrong"
}
```

---

## Список комнат пользователя

- URL: `/api/v1/joined_rooms/`
- Метод: `GET` или `POST`
- Требуется авторизация

Эндпойнт возвращает список комнат, в которых у пользователя статус участника `join` или `invite`.

Пример ответа:

```json
[
  {
    "room_id": "!uuid:example.com",
    "name": "Моя комната",
    "creator": "@alice:example.com",
    "topic": null,
    "cdate": 1690000000
  }
]
```

---

## Синхронизация событий

- URL: `/api/v1/sync/`
- Метод: `GET`
- Требуется авторизация
- Параметры запроса:
  - `since` (integer, необязательно) — метка времени в секундах, возвращаются события после этого времени

Пример запроса:

```http
GET /api/v1/sync/?since=1690000000
```

Пример ответа:

```json
{
  "next_batch": 1690000001,
  "rooms": {
    "invite": {
      "!roomid:example.com": {
        "invite_state": {
          "events": []
        }
      }
    },
    "join": {
      "!roomid:example.com": {
        "events": [
          {
            "event_id": "$uuid",
            "json": {
              "event_id": "$uuid",
              "type": "m.text",
              "room_id": "!roomid:example.com",
              "sender": "@alice:example.com",
              "origin_server_ts": 1690000000000,
              "content": {
                "body": "Hello",
                "room_id": "!roomid:example.com",
                "sender": "@alice:example.com"
              }
            }
          }
        ]
      }
    }
  }
}
```

---

## Действия с комнатами

Эндпойнты находятся по пути `/api/v1/rooms/` и требуют авторизации и корректный идентификатор комнаты.

### Отправка сообщения или создание события комнаты

- URL: `/api/v1/rooms/`
- Метод: `POST`
- Тело:
  - `room_id` (string) — идентификатор комнаты
  - `msgtype` (string) — тип события, для сообщения используйте `m.text`
  - `body` (string) — текст сообщения

Пример тела запроса:

```json
{
  "room_id": "!roomid:example.com",
  "msgtype": "m.text",
  "body": "Привет мир"
}
```

Успешный ответ:

```json
{
  "status": "ok",
  "event_id": "$uuid"
}
```

Ошибки:

```json
{
  "error": "Room not found"
}
```

```json
{
  "error": "Sending a message is prohibited"
}
```

---

### Список участников комнаты

- URL: `/api/v1/rooms/{roomId}/members/`
- Метод: `GET` или `POST`
- Требуется авторизация

Возвращает записи членства для указанной комнаты.

Пример ответа:

```json
[
  {
    "event_id": "$uuid",
    "user_id": "@alice:example.com",
    "sender": "@alice:example.com",
    "room_id": "!roomid:example.com",
    "membership": "join"
  }
]
```

---

### Приглашение пользователя в комнату

- URL: `/api/v1/rooms/{roomId}/invite/`
- Метод: `POST`
- Требуется авторизация
- Тело:
  - `user_id` (string) — полный идентификатор пользователя, например `@bob:example.com`

Пример тела запроса:

```json
{
  "user_id": "@bob:example.com"
}
```

Успешный ответ:

```json
{
  "status": "ok"
}
```

Если пользователь не найден, ответ будет:

```json
{
  "error": "Unable to find user"
}
```

---

### Принять приглашение в комнату

- URL: `/api/v1/rooms/{roomId}/accept/`
- Метод: `POST`
- Требуется авторизация

Этот эндпойнт обновляет статус участника на `join`.

Успешный ответ:

```json
{
  "status": "ok"
}
```

---

### Забанить пользователя в комнате

- URL: `/api/v1/rooms/{roomId}/ban/`
- Метод: `POST`
- Требуется авторизация
- Тело:
  - `user_id` (string) — полный идентификатор пользователя для бана

Пример тела запроса:

```json
{
  "user_id": "@bob:example.com"
}
```

Успешный ответ:

```json
{
  "status": "ok"
}
```

---

### Разбанить пользователя в комнате

- URL: `/api/v1/rooms/{roomId}/unban/`
- Метод: `POST`
- Требуется авторизация
- Тело:
  - `user_id` (string)

Успешный ответ:

```json
{
  "status": "ok"
}
```

---

## Примечания

- Все данные запроса должны быть в JSON формате.
- API использует JWT-токены, хранящиеся в таблице `access_tokens`.
- Неверный или отсутствующий токен возвращает HTTP `401` и JSON с ошибкой.
- Формат `user_id`: `@login:domain`, где домен задаётся через `WCO::$domain`.
- Идентификаторы комнат создаются в формате `!<uuid>:domain`.
