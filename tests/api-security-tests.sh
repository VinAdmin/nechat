#!/usr/bin/env bash
#
# Автотест API Nechat: проверка исправлений безопасности из security-fixes-report.md.
# Покрывает блоки 1, 2, 3, 5 из security-fixes-test-plan.md (блок 4 — chunked file_size —
# оставлен как ручной кейс, см. план).
#
# Требования: curl, jq.
# Запуск:     BASE_URL=https://chat.loc ./api-security-tests.sh
#             (по умолчанию BASE_URL=https://chat.loc, самоподписанный сертификат — curl идёт с -k)
#
set -u

BASE_URL="${BASE_URL:-https://chat.loc}"
HOST="$(echo "$BASE_URL" | sed -E 's#^https?://##; s#/.*$##')"
CURL_OPTS=(-s -k)
RUN_ID="$(date +%s)$RANDOM"
PASS=0
FAIL=0

# ---------- утилиты ----------

info()  { echo "  $*"; }
ok()    { PASS=$((PASS+1)); echo "[PASS] $*"; }
bad()   { FAIL=$((FAIL+1)); echo "[FAIL] $*"; }

# api_json METHOD PATH TOKEN JSON_BODY -> печатает "HTTP_CODE\nBODY"
api_json() {
    local method="$1" path="$2" token="$3" body="$4"
    local args=(-X "$method" "${CURL_OPTS[@]}" -H "Content-Type: application/json" -w '\n%{http_code}')
    [ -n "$token" ] && args+=(-H "Authorization: Bearer $token")
    [ -n "$body" ] && args+=(-d "$body")
    curl "${args[@]}" "$BASE_URL$path"
}

# api_get PATH TOKEN
api_get() {
    local path="$1" token="$2"
    local args=(-X GET "${CURL_OPTS[@]}" -w '\n%{http_code}')
    [ -n "$token" ] && args+=(-H "Authorization: Bearer $token")
    curl "${args[@]}" "$BASE_URL$path"
}

# api_form PATH TOKEN [-F ... -F ...]
api_form() {
    local path="$1" token="$2"; shift 2
    local args=(-X POST "${CURL_OPTS[@]}" -w '\n%{http_code}' -H "Authorization: Bearer $token")
    curl "${args[@]}" "$@" "$BASE_URL$path"
}

# split_response VAR_BODY VAR_CODE <<< "$raw"  — последняя строка ответа curl -w это код.
# В dev-окружении (debug=true) к каждому ответу приклеивается Tracy debug bar (HTML/JS ПОСЛЕ
# JSON), а иногда PHP пишет warning/notice ДО JSON (см. известную проблему с preg_replace в
# Events.php). Поэтому не гадаем по позиции строки, а берём первую строку, целиком похожую на
# JSON-объект/массив.
split_body()  { echo "$1" | sed '$d' | grep -m1 -E '^\{.*\}$|^\[.*\]$'; }
split_code()  { echo "$1" | tail -n1; }

register_and_login() {
    local login="$1" password="TestPass123!"
    api_json POST "/api/v1/registration/" "" "$(jq -n --arg l "$login" --arg p "$password" '{login:$l,password:$p}')" >/dev/null

    local raw code body token
    raw="$(api_json POST "/api/v1/authorization/" "" "$(jq -n --arg l "$login" --arg p "$password" '{login:$l,password:$p}')")"
    body="$(split_body "$raw")"; code="$(split_code "$raw")"
    if [ "$code" != "200" ]; then
        echo "FATAL: не удалось авторизовать пользователя $login (HTTP $code): $body" >&2
        exit 1
    fi
    token="$(echo "$body" | jq -r '.token')"
    echo "$token"
}

# ---------- подготовка ----------

echo "== Подготовка тестовых пользователей и комнаты (RUN_ID=$RUN_ID) =="

LOGIN_A="qa_a_${RUN_ID}"; LOGIN_B="qa_b_${RUN_ID}"; LOGIN_C="qa_c_${RUN_ID}"; LOGIN_D="qa_d_${RUN_ID}"
TOKEN_A="$(register_and_login "$LOGIN_A")"
TOKEN_B="$(register_and_login "$LOGIN_B")"
TOKEN_C="$(register_and_login "$LOGIN_C")"
TOKEN_D="$(register_and_login "$LOGIN_D")"
USER_A="@${LOGIN_A}:${HOST}"
USER_C="@${LOGIN_C}:${HOST}"
USER_D="@${LOGIN_D}:${HOST}"
info "Пользователи: A=$USER_A, B=@${LOGIN_B}:${HOST}, C=$USER_C, D=$USER_D"

# B и C должны состоять ХОТЬ В КАКОЙ-ТО комнате, иначе actionRooms() отсекает их
# раньше проверяемой логики через Rooms::accessRoom() с другим текстом ошибки
# ("Messages are not allowed in this room.") — это отдельный, более старый и низкоприоритетный
# баг (см. security-fixes-report.md, раздел "Сознательно не сделано" не про него, это ещё один
# найденный при подготовке автотеста, room-agnostic gate в actionRooms()). Чтобы тестировать
# именно наш фикс (членство в КОНКРЕТНОЙ комнате), даём B и C членство в отдельной decoy-комнате.
api_json POST "/api/v1/createRoom/" "$TOKEN_B" "$(jq -n --arg n "QA Decoy B ${RUN_ID}" '{name:$n}')" >/dev/null
api_json POST "/api/v1/createRoom/" "$TOKEN_C" "$(jq -n --arg n "QA Decoy C ${RUN_ID}" '{name:$n}')" >/dev/null

ROOM_NAME="QA Room ${RUN_ID}"
raw="$(api_json POST "/api/v1/createRoom/" "$TOKEN_A" "$(jq -n --arg n "$ROOM_NAME" '{name:$n}')")"
code="$(split_code "$raw")"
[ "$code" = "200" ] || { echo "FATAL: не удалось создать комнату (HTTP $code)"; exit 1; }

raw="$(api_get "/api/v1/joined_rooms/" "$TOKEN_A")"
body="$(split_body "$raw")"
ROOM_ID="$(echo "$body" | jq -r --arg n "$ROOM_NAME" '.[] | select(.name==$n) | .room_id')"
if [ -z "$ROOM_ID" ] || [ "$ROOM_ID" = "null" ]; then
    echo "FATAL: не удалось найти room_id только что созданной комнаты"; exit 1
fi
info "ROOM_ID=$ROOM_ID"

# C: приглашаем, затем баним. D: приглашаем и оставляем как есть (не accept).
api_json POST "/api/v1/rooms/$ROOM_ID/invite/" "$TOKEN_A" "$(jq -n --arg u "$USER_C" '{user_id:$u}')" >/dev/null
api_json POST "/api/v1/rooms/$ROOM_ID/ban/"    "$TOKEN_A" "$(jq -n --arg u "$USER_C" '{user_id:$u}')" >/dev/null
api_json POST "/api/v1/rooms/$ROOM_ID/invite/" "$TOKEN_A" "$(jq -n --arg u "$USER_D" '{user_id:$u}')" >/dev/null
info "C забанен, D приглашён (не вступал), B не состоит в комнате вообще"

echo

# ---------- Блок 1: отправка сообщений/файлов в чужую комнату ----------

echo "== Блок 1: членство при отправке сообщений/файлов =="

raw="$(api_json POST "/api/v1/rooms/" "$TOKEN_A" "$(jq -n --arg r "$ROOM_ID" '{room_id:$r,msgtype:"m.text",body:"hello from A"}')")"
body="$(split_body "$raw")"; code="$(split_code "$raw")"
[ "$code" = "200" ] && [ "$(echo "$body" | jq -r .status)" = "ok" ] \
    && ok "1.1 A (участник) успешно отправляет сообщение" \
    || bad "1.1 A (участник) не смог отправить сообщение: HTTP $code $body"

raw="$(api_json POST "/api/v1/rooms/" "$TOKEN_B" "$(jq -n --arg r "$ROOM_ID" '{room_id:$r,msgtype:"m.text",body:"hello from B"}')")"
body="$(split_body "$raw")"
[ "$(echo "$body" | jq -r '.error // empty')" = "Sending a message is prohibited" ] \
    && ok "1.2 B (не участник) не может отправить сообщение в чужую комнату" \
    || bad "1.2 B смог отправить сообщение в чужую комнату (уязвимость не закрыта): $body"

TMP_IMG="$(mktemp /tmp/qa_img_XXXX.png)"
# 1x1 прозрачный PNG
base64 -d > "$TMP_IMG" <<< "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII="

raw="$(api_form "/api/v1/rooms/" "$TOKEN_B" -F "room_id=$ROOM_ID" -F "msgtype=m.file" -F "file=@${TMP_IMG};type=image/png")"
body="$(split_body "$raw")"
[ "$(echo "$body" | jq -r '.error // empty')" = "Sending a message is prohibited" ] \
    && ok "1.3 B (не участник) не может загрузить файл в чужую комнату" \
    || bad "1.3 B смог загрузить файл в чужую комнату (уязвимость не закрыта): $body"

MARK_IMG="QAIMG_${RUN_ID}"
raw="$(api_form "/api/v1/rooms/" "$TOKEN_A" -F "room_id=$ROOM_ID" -F "msgtype=m.file" -F "file=@${TMP_IMG};type=image/png" -F "body=${MARK_IMG}")"
body="$(split_body "$raw")"; code="$(split_code "$raw")"
[ "$code" = "200" ] && [ "$(echo "$body" | jq -r .status)" = "ok" ] \
    && ok "1.4 A (участник) успешно загружает файл" \
    || bad "1.4 A не смог загрузить файл: HTTP $code $body"

# file_url в ответ на отправку не приходит — достаём его поиском по уникальной метке в body
raw="$(api_get "/api/v1/search/?room_id=${ROOM_ID}&q=${MARK_IMG}" "$TOKEN_A")"
FILE_URL_IMG="$(split_body "$raw" | jq -r '.[0].json.content.file_url // empty')"

raw="$(api_json POST "/api/v1/rooms/" "$TOKEN_C" "$(jq -n --arg r "$ROOM_ID" '{room_id:$r,msgtype:"m.text",body:"hi"}')")"
body="$(split_body "$raw")"
[ "$(echo "$body" | jq -r '.error // empty')" = "Sending a message is prohibited" ] \
    && ok "1.5 C (забанен) по-прежнему не может отправить сообщение" \
    || bad "1.5 регресс: забаненный C смог отправить сообщение: $body"

raw="$(api_json POST "/api/v1/rooms/" "$TOKEN_D" "$(jq -n --arg r "$ROOM_ID" '{room_id:$r,msgtype:"m.text",body:"hi"}')")"
body="$(split_body "$raw")"
[ "$(echo "$body" | jq -r '.error // empty')" = "Sending a message is prohibited" ] \
    && ok "1.6 D (только приглашён, не вступил) не может отправить сообщение" \
    || bad "1.6 регресс: приглашённый, но не вступивший D смог отправить сообщение: $body"

echo

# ---------- Блок 2: хранимый XSS через отдачу файлов ----------

echo "== Блок 2: Content-Disposition при отдаче файлов =="

TMP_HTML="$(mktemp /tmp/qa_evil_XXXX.html)"
echo '<script>document.title="XSS-EXECUTED"</script>' > "$TMP_HTML"

MARK_HTML="QAHTML_${RUN_ID}"
raw="$(api_form "/api/v1/rooms/" "$TOKEN_A" -F "room_id=$ROOM_ID" -F "msgtype=m.file" -F "file=@${TMP_HTML};type=text/html;filename=evil.html" -F "body=${MARK_HTML}")"
body="$(split_body "$raw")"
raw2="$(api_get "/api/v1/search/?room_id=${ROOM_ID}&q=${MARK_HTML}" "$TOKEN_A")"
FILE_URL_HTML="$(split_body "$raw2" | jq -r '.[0].json.content.file_url // empty')"
if [ -z "$FILE_URL_HTML" ]; then
    bad "2.0 не удалось загрузить тестовый html-файл, дальнейшие проверки блока 2 (html) пропущены: $body"
else
    disp="$(curl -s -k -I -H "Authorization: Bearer $TOKEN_A" "$BASE_URL$FILE_URL_HTML" | tr -d '\r' | grep -i '^Content-Disposition:')"
    echo "$disp" | grep -qi 'attachment' \
        && ok "2.1-2.3 html-файл отдаётся с Content-Disposition: attachment ($disp)" \
        || bad "2.1-2.3 html-файл отдан НЕ как attachment (возможен XSS): $disp"
fi

if [ -n "$FILE_URL_IMG" ]; then
    disp="$(curl -s -k -I -H "Authorization: Bearer $TOKEN_A" "$BASE_URL$FILE_URL_IMG" | tr -d '\r' | grep -i '^Content-Disposition:')"
    echo "$disp" | grep -qi 'inline' \
        && ok "2.4 регресс: обычная картинка по-прежнему отдаётся inline ($disp)" \
        || bad "2.4 регресс: картинка перестала отдаваться inline: $disp"
else
    bad "2.4 пропущен — не получен file_url картинки из блока 1.4"
fi

echo

# ---------- Блок 3: проверка членства в search / getTyping / members ----------

echo "== Блок 3: членство в поиске / индикаторе печати / списке участников =="

raw="$(api_get "/api/v1/search/?room_id=${ROOM_ID}&q=hello" "$TOKEN_B")"
body="$(split_body "$raw")"; code="$(split_code "$raw")"
[ "$code" = "403" ] && [ "$(echo "$body" | jq -r '.error // empty')" = "Access denied" ] \
    && ok "3.1 B (не участник) не может искать в чужой комнате" \
    || bad "3.1 B смог искать в чужой комнате (уязвимость не закрыта): HTTP $code $body"

raw="$(api_get "/api/v1/search/?room_id=${ROOM_ID}&q=hello" "$TOKEN_A")"
body="$(split_body "$raw")"; code="$(split_code "$raw")"
[ "$code" = "200" ] && [ "$(echo "$body" | jq 'length')" -ge 1 ] \
    && ok "3.2 регресс: A (участник) находит своё сообщение через поиск" \
    || bad "3.2 регресс: A не смог найти сообщение через поиск: HTTP $code $body"

raw="$(api_get "/api/v1/getTyping/?room_id=${ROOM_ID}" "$TOKEN_B")"
body="$(split_body "$raw")"; code="$(split_code "$raw")"
[ "$code" = "403" ] \
    && ok "3.3 B (не участник) не может смотреть индикатор печати в чужой комнате" \
    || bad "3.3 B получил доступ к getTyping чужой комнаты: HTTP $code $body"

raw="$(api_get "/api/v1/getTyping/?room_id=${ROOM_ID}" "$TOKEN_A")"
code="$(split_code "$raw")"
[ "$code" = "200" ] \
    && ok "3.4 регресс: A (участник) получает getTyping своей комнаты" \
    || bad "3.4 регресс: A не смог получить getTyping своей комнаты: HTTP $code"

raw="$(api_json POST "/api/v1/rooms/$ROOM_ID/members/" "$TOKEN_B" "")"
body="$(split_body "$raw")"
[ "$(echo "$body" | jq -r '.error // empty')" = "Access denied" ] \
    && ok "3.5 B (не участник) не может получить список участников чужой комнаты" \
    || bad "3.5 B получил список участников чужой комнаты (уязвимость не закрыта): $body"

raw="$(api_json POST "/api/v1/rooms/$ROOM_ID/members/" "$TOKEN_A" "")"
body="$(split_body "$raw")"
[ "$(echo "$body" | jq 'type=="array"')" = "true" ] \
    && ok "3.6 регресс: A (участник) получает список участников своей комнаты" \
    || bad "3.6 регресс: A не смог получить список участников своей комнаты: $body"

echo

# ---------- Блок 5: срок действия токена и подпись ----------

echo "== Блок 5: токены доступа =="

payload="$(echo "$TOKEN_A" | cut -d. -f2)"
# добиваем base64url до кратности 4 паддингом
pad=$(( (4 - ${#payload} % 4) % 4 ))
payload_padded="${payload}$(printf '=%.0s' $(seq 1 $pad) 2>/dev/null)"
decoded="$(echo "$payload_padded" | tr '_-' '/+' | base64 -d 2>/dev/null)"
if echo "$decoded" | jq -e 'has("exp")' >/dev/null 2>&1; then
    bad "5.1 в payload токена всё ещё есть поле exp: $decoded"
else
    ok "5.1 в payload токена нет поля exp (срок действия по времени убран)"
fi

TAMPERED="${TOKEN_A%?}X"
raw="$(api_get "/api/v1/joined_rooms/" "$TAMPERED")"
code="$(split_code "$raw")"
[ "$code" = "401" ] \
    && ok "5.3 запрос с испорченной подписью токена отклонён (401)" \
    || bad "5.3 запрос с испорченной подписью токена НЕ отклонён: HTTP $code"

raw="$(api_json POST "/api/v1/logout/" "$TOKEN_D" "")"
code="$(split_code "$raw")"
raw2="$(api_get "/api/v1/joined_rooms/" "$TOKEN_D")"
code2="$(split_code "$raw2")"
[ "$code" = "200" ] && [ "$code2" = "401" ] \
    && ok "5.4 после logout токен D больше не работает" \
    || bad "5.4 регресс: logout не инвалидировал токен (logout HTTP $code, повторный запрос HTTP $code2)"

echo

# ---------- итог ----------

rm -f "$TMP_IMG" "$TMP_HTML" 2>/dev/null

echo "======================================"
echo "PASS: $PASS   FAIL: $FAIL"
echo "======================================"
[ "$FAIL" -eq 0 ]
