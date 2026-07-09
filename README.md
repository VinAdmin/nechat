# Nechat
Внимание! Проект находится в разработки.

Чат-система на PHP (WCO framework) с Vue.js фронтендом. Сообщения, комнаты, файлы, инвайты, Matrix-подобные события.

## Требования

- PHP 8.1+
- MariaDB 11.8+
- Composer 2.x
- Apache/Nginx (с mod_rewrite)

## Установка

### 1. Клонировать репозиторий

```bash
git clone <repo-url> /var/www/chat
cd /var/www/chat
```

### 2. Установить зависимости Composer

```bash
composer install
```

### 3. Настроить базу данных

Скопировать и отредактировать конфиг БД:

```bash
cp config/db.exampl.php config/db.php
```

Заполнить `config/db.php`:

```php
$config_db = [
    'default' => [
        'db'       => 'mysql',
        'host'     => 'localhost',
        'db_name'  => 'chat',
        'login'    => 'chat',
        'password' => 'your_password'
    ],
];
```

### 4. Создать таблицы

Выполнить SQL в MySQL, MariaDB:

```sql
CREATE TABLE users (
    user_id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500) DEFAULT NULL
);

CREATE TABLE access_tokens (
    token VARCHAR(500) PRIMARY KEY,
    user_id VARCHAR(255) NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

CREATE TABLE rooms (
    room_id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    creator VARCHAR(255) NOT NULL,
    topic TEXT DEFAULT NULL,
    cdate INT UNSIGNED NOT NULL,
    FOREIGN KEY (creator) REFERENCES users(user_id)
);

CREATE TABLE room_memberships (
    event_id VARCHAR(255) PRIMARY KEY,
    room_id VARCHAR(255) NOT NULL,
    user_id VARCHAR(255) NOT NULL,
    sender VARCHAR(255) NOT NULL,
    membership ENUM('join','invite','ban') NOT NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id)
);

CREATE TABLE events (
    event_id VARCHAR(255) PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    room_id VARCHAR(255) NOT NULL,
    sender VARCHAR(255) NOT NULL,
    received_ts INT UNSIGNED NOT NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id)
);

CREATE TABLE event_json (
    event_id VARCHAR(255) PRIMARY KEY,
    room_id VARCHAR(255) NOT NULL,
    json TEXT NOT NULL,
    FOREIGN KEY (event_id) REFERENCES events(event_id),
    FOREIGN KEY (room_id) REFERENCES rooms(room_id)
);
```

### 5. Настроить конфиг приложения

Скопировать и отредактировать:

```bash
cp config/config.exampl.php config/config.php
```
Генерация ключа для JWT:

```bash
openssl rand -hex 32
```

Установить `SECRET_KEY` (JWT signing key):

```php
define('SECRET_KEY', 'your-random-secret-key');
```

### 6. Настроить веб-сервер

#### Apache

Создать `.htaccess` в `web/` (если отсутствует):

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

DocumentRoot должен указывать на `web/`.

#### Nginx

```nginx
server {
    listen 80;
    server_name chat.example.com;
    root /var/www/chat/web;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 7. Создать директорию для загрузок

```bash
mkdir -p data/uploads
chmod 775 data/uploads
```

### 8. Проверить права доступа

```bash
chown -R www-data:www-data data/uploads
```

### 9. Открыть в браузере

```
http://chat.example.com/
```

## Разработка

### Структура проекта

```
config/          - конфигурация приложения и БД
  config.php     - основная конфигурация (template, SECRET_KEY)
  db.php         - подключение к БД
domain/
  default/       - шаблон по умолчанию
    controllers/ - PHP-контроллеры (SiteController)
    modules/api/controllers/ - API-контроллеры (V1Controller)
    views/       - шаблоны представлений (chat.php)
models/          - модели (Users, Rooms, Events, и т.д.)
web/             - DocumentRoot
  default/       - статика (css, js, изображения)
data/uploads/    - загруженные пользователями файлы
vendor/          - Composer-зависимости
```

### Фронтенд

Фронтенд — SPA на Vue.js 3 (CDN, без сборщика). Основной код:

- `web/default/js/rooms.js` — Vue-приложение (комнаты, сообщения, синхронизация)
- `web/default/css/style.css` — стили
- `domain/default/views/index/chat.php` — HTML-шаблон с Vue-директивами

### API

Документация API — в [API.md](API.md).

Основные эндпойнты:
- `POST /api/v1/registration/` — регистрация
- `POST /api/v1/authorization/` — авторизация (возвращает JWT)
- `GET /api/v1/sync/` — синхронизация событий (long polling)
- `GET|POST /api/v1/joined_rooms/` — список комнат
- `POST /api/v1/rooms/` — создание события (сообщение/файл)
- `POST /api/v1/createRoom/` — создание комнаты
- `GET|POST /api/v1/rooms/{roomId}/` — управление комнатой
- `POST /api/v1/rooms/{roomId}/accept/` — принять приглашение
- `POST /api/v1/rooms/{roomId}/invite/` — пригласить пользователя
- `POST /api/v1/rooms/{roomId}/ban/` — забанить
- `POST /api/v1/rooms/{roomId}/unban/` — разбанить
- `POST /api/v1/delete_message/` — удалить сообщение
- `GET /f/{filename}` — скачать файл

### Кэширование

- `localStorage` — сообщения (`messagesStore`), непрочитанные (`unreadCounts`)
- `sessionStorage` — токен синхронизации (`syncToken`)
- Синхронизация: каждые 1 сек, полный рефреш комнат — каждые 60 сек

## Лицензия

MIT — см. [LICENSE](LICENSE).
