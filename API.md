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

---

### Обновление комнаты

- URL: `/api/v1/rooms/{roomId}/update/`
- Метод: `POST`
- Требуется авторизация
- Доступно только создателю комнаты

Тело (все поля опциональны):

- `name` (string) — новое название
- `topic` (string) — новая тема
- `join_rule` (string) — `public` или `invite`

Пример тела запроса:

```json
{
  "name": "Новое название",
  "topic": "Обсуждение проекта",
  "join_rule": "public"
}
```

Успешный ответ:

```json
{
  "status": "ok"
}
```

---

### Загрузка аватара комнаты

- URL: `/api/v1/rooms/{roomId}/upload_avatar/`
- Метод: `POST`
- Требуется авторизация
- Доступно только создателю комнаты
- Формат: `multipart/form-data`

Поля:

- `file` (file) — изображение. Допустимые расширения: jpg, jpeg, png, gif, webp

Успешный ответ:

```json
{
  "status": "ok",
  "file_url": "/f/1680000000_abcdef123456_avatar.png"
}
```

---

### Удаление сообщения

- URL: `/api/v1/rooms/{roomId}/delete/`
- Метод: `POST`
- Требуется авторизация
- Удалить может автор сообщения или создатель комнаты

Тело:

- `event_id` (string) — идентификатор события

Пример тела запроса:

```json
{
  "event_id": "$uuid"
}
```

Успешный ответ:

```json
{
  "status": "ok"
}
```

---

## Профиль пользователя

- URL: `/api/v1/profile/`
- Метод: `GET` или `POST`
- Требуется авторизация

### GET

Возвращает данные профиля текущего пользователя:

```json
{
  "user_id": "@alice:example.com",
  "name": "alice",
  "avatar_url": "/f/avatar.png",
  "email": ""
}
```

### POST

Обновление профиля. Формат: `application/json` или `multipart/form-data`.

Поля:

- `avatar_url` (string) — URL аватара
- `avatar` (file) — файл изображения (jpg, jpeg, png, gif, webp). Если передан, `avatar_url` игнорируется
- `new_password` (string) — новый пароль
- `old_password` (string) — текущий пароль (обязателен при смене пароля)

Успешный ответ:

```json
{
  "status": "ok"
}
```

---

## Выход

- URL: `/api/v1/logout/`
- Метод: `POST`
- Требуется авторизация

Удаляет текущий токен доступа.

Успешный ответ:

```json
{
  "status": "ok"
}
```

---

## Публичные комнаты

- URL: `/api/v1/public_rooms/`
- Метод: `GET`
- Требуется авторизация

Параметры запроса:

- `q` (string, опционально) — поисковый запрос для фильтрации по названию

Пример запроса:

```http
GET /api/v1/public_rooms/?q=chat
```

Успешный ответ:

```json
[
  {
    "room_id": "!uuid:example.com",
    "name": "My Room",
    "topic": "Room topic",
    "join_rule": "public",
    "member_count": 5
  }
]
```

---

## Присоединиться к публичной комнате

- URL: `/api/v1/join_room/`
- Метод: `POST`
- Требуется авторизация

Тело:

- `room_id` (string) — идентификатор комнаты

Пример тела запроса:

```json
{
  "room_id": "!uuid:example.com"
}
```

Успешный ответ:

```json
{
  "status": "ok"
}
```

---

## Отправка файла

Файлы отправляются на тот же эндпойнт, что и текстовые сообщения: `POST /api/v1/rooms/`.

Формат: `multipart/form-data`.

Поля:

- `room_id` (string) — идентификатор комнаты
- `msgtype` (string) — `m.file`
- `file` (file) — загружаемый файл
- `body` (string, опционально) — подпись к файлу
- `reply_to` (string, опционально) — event_id сообщения, на которое отвечаете

### Chunked-загрузка (для файлов >1 MB)

Вместо одного файла отправляется несколько запросов — по одному на каждый чанк.

Поля каждого запроса:

- `room_id` (string) — идентификатор комнаты
- `msgtype` (string) — `m.file`
- `upload_id` (string) — уникальный идентификатор загрузки (напр. `1670000000_random`)
- `chunk_index` (int) — номер чанка (1-based)
- `chunk_count` (int) — общее количество чанков
- `file_name` (string) — оригинальное имя файла
- `file_size` (int) — полный размер файла
- `file` (file) — бинарные данные чанка
- `body` (string, опционально) — добавляется только в последний чанк

Ответ на промежуточные чанки:

```json
{
  "status": "chunk_received",
  "chunk_index": 1,
  "chunk_count": 3
}
```

Ответ на последний чанк (успешная загрузка):

```json
{
  "status": "ok",
  "event_id": "$uuid"
}
```

---

## Версия фронтенда

- URL: `/api/v1/version/`
- Метод: `GET`
- Авторизация не требуется

Возвращает MD5-хеш файла `rooms.js` для автообновления страницы при изменении кода.

Успешный ответ:

```json
{
  "hash": "d41d8cd98f00b204e9800998ecf8427e"
}
```

---

## Примечания

- Все данные запроса должны быть в JSON формате.
- API использует JWT-токены, хранящиеся в таблице `access_tokens`.
- Неверный или отсутствующий токен возвращает HTTP `401` и JSON с ошибкой.
- Формат `user_id`: `@login:domain`, где домен задаётся через `WCO::$domain`.
- Идентификаторы комнат создаются в формате `!<uuid>:domain`.

---

## Присутствие (онлайн-статус)

- URL: `/api/v1/presence/`
- Метод: `POST`
- Требуется авторизация

Heartbeat присутствия. Отправьте запрос каждые 10-15 секунд для поддержания онлайн-статуса. Возвращает список ID онлайн-пользователей.

Успешный ответ:

```json
{
  "online": ["@alice:example.com", "@bob:example.com"]
}
```

---

## Индикатор набора текста (typing)

### Отправить индикатор

- URL: `/api/v1/typing/`
- Метод: `POST`
- Требуется авторизация
- Тело:
  - `room_id` (string) — идентификатор комнаты

Успешный ответ:

```json
{
  "status": "ok",
  "typing": ["@bob:example.com"]
}
```

### Остановить индикатор

- URL: `/api/v1/typing/`
- Метод: `DELETE`
- Требуется авторизация
- Тело:
  - `room_id` (string)

### Получить список печатающих

- URL: `/api/v1/getTyping/?room_id=!roomid:example.com`
- Метод: `GET`
- Требуется авторизация

Успешный ответ:

```json
{
  "typing": ["@bob:example.com"]
}
```

---

## Поиск сообщений

- URL: `/api/v1/search/`
- Метод: `GET`
- Требуется авторизация
- Параметры:
  - `room_id` (string) — идентификатор комнаты
  - `q` (string) — поисковый запрос

Пример:

```
GET /api/v1/search/?room_id=!roomid:example.com&q=привет
```

Успешный ответ:

```json
[
  {
    "event_id": "$uuid",
    "json": {
      "event_id": "$uuid",
      "type": "m.text",
      "sender": "@alice:example.com",
      "content": {
        "body": "Привет мир!",
        "sender": "@alice:example.com"
      }
    }
  }
]
```

---

## Редактирование сообщения

- URL: `/api/v1/editMessage/`
- Метод: `POST`
- Требуется авторизация
- Редактировать можно только свои сообщения

Тело:

- `event_id` (string) — идентификатор события
- `room_id` (string) — идентификатор комнаты
- `body` (string) — новый текст сообщения

Пример тела запроса:

```json
{
  "event_id": "$uuid",
  "room_id": "!roomid:example.com",
  "body": "Исправленное сообщение"
}
```

Успешный ответ:

```json
{
  "status": "ok"
}
```

---

## Личные сообщения (DM)

- URL: `/api/v1/directMessage/`
- Метод: `POST`
- Требуется авторизация

Создаёт или возвращает существующий приватный диалог между двумя пользователями.

Тело:

- `user_id` (string) — ID пользователя для диалога

Пример тела запроса:

```json
{
  "user_id": "@bob:example.com"
}
```

Успешный ответ:

```json
{
  "status": "ok",
  "room_id": "!uuid:example.com"
}
```
