<?php
namespace app\models;

/**
 * Чистый помощник для работы с уровнями доступа комнаты (событие m.room.power_levels).
 *
 * Не обращается к БД — только преобразует массивы. Чтение состояния из БД делает Rooms,
 * запись нового события — Events::setPowerLevels().
 *
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class PowerLevels{
    /** Уровень создателя комнаты и верхняя граница уровней. */
    public const OWNER_LEVEL = 100;

    /** Значения по умолчанию для content события m.room.power_levels. */
    public const DEFAULTS = [
        'users'          => [],
        'users_default'  => 0,
        'events_default' => 0,
        'invite'         => 0,
        'kick'           => 50,
        'ban'            => 50,
        'redact'         => 50,
        'state_default'  => 50,
        'power_levels'   => 100,
    ];

    /** Числовые пороги, которые можно менять через { "thresholds": {...} }. */
    public const THRESHOLD_KEYS = [
        'events_default', 'invite', 'kick', 'ban', 'redact', 'state_default', 'power_levels', 'users_default',
    ];

    /**
     * Сливает content события с дефолтами и нормализует типы.
     *
     * @param array|null $content content события m.room.power_levels
     * @return array нормализованный набор уровней
     */
    public static function parse(?array $content): array {
        $content = is_array($content) ? $content : [];
        $result = self::DEFAULTS;

        foreach (self::THRESHOLD_KEYS as $key) {
            if (isset($content[$key]) && is_numeric($content[$key])) {
                $result[$key] = (int) $content[$key];
            }
        }

        $users = [];
        if (isset($content['users']) && is_array($content['users'])) {
            foreach ($content['users'] as $userId => $level) {
                if (is_string($userId) && is_numeric($level)) {
                    $users[$userId] = (int) $level;
                }
            }
        }
        $result['users'] = $users;

        return $result;
    }

    /**
     * Эффективный уровень пользователя. Создатель комнаты всегда >= OWNER_LEVEL.
     *
     * @param array  $levels  нормализованный набор (из parse)
     * @param string $userId
     * @param string $creator user_id создателя комнаты (rooms.creator)
     */
    public static function levelForUser(array $levels, string $userId, string $creator): int {
        if ($userId !== '' && $userId === $creator) {
            return max(self::OWNER_LEVEL, $levels['users'][$userId] ?? self::OWNER_LEVEL);
        }
        return $levels['users'][$userId] ?? $levels['users_default'] ?? 0;
    }

    /**
     * Порог, необходимый для действия.
     *
     * @param string $action ключ из THRESHOLD_KEYS либо алиас 'unban' (== 'ban')
     */
    public static function threshold(array $levels, string $action): int {
        if ($action === 'unban') {
            $action = 'ban';
        }
        return $levels[$action] ?? self::DEFAULTS[$action] ?? self::OWNER_LEVEL;
    }

    /**
     * Проверяет право назначить участнику targetUserId уровень newLevel.
     *
     * @return array{ok: bool, error: string}
     */
    public static function canAssignLevel(
        int $actorLevel,
        string $actorId,
        string $targetUserId,
        int $targetCurrentLevel,
        int $newLevel
    ): array {
        if ($newLevel < 0 || $newLevel > self::OWNER_LEVEL) {
            return ['ok' => false, 'error' => 'Level must be between 0 and 100'];
        }
        if ($newLevel > $actorLevel) {
            return ['ok' => false, 'error' => 'Cannot assign a level above your own'];
        }
        if ($targetUserId !== $actorId && $targetCurrentLevel >= $actorLevel) {
            return ['ok' => false, 'error' => 'Cannot modify a user with an equal or higher level'];
        }
        return ['ok' => true, 'error' => ''];
    }

    /**
     * Проверяет право изменить пороги: ни один порог не выше уровня актора.
     *
     * @param array<string,mixed> $newThresholds
     * @return array{ok: bool, error: string}
     */
    public static function canModifyThresholds(int $actorLevel, array $newThresholds): array {
        foreach ($newThresholds as $key => $value) {
            if (!in_array($key, self::THRESHOLD_KEYS, true)) {
                continue;
            }
            if (!is_numeric($value) || $value < 0 || $value > self::OWNER_LEVEL) {
                return ['ok' => false, 'error' => 'Threshold must be between 0 and 100'];
            }
            if ((int) $value > $actorLevel) {
                return ['ok' => false, 'error' => 'Cannot set a threshold above your own level'];
            }
        }
        return ['ok' => true, 'error' => ''];
    }

    /**
     * Применяет изменение к набору уровней и возвращает новый полный content.
     *
     * @param array $levels текущий нормализованный набор (из parse)
     * @param array $change ['user_id'=>..., 'level'=>...] либо ['thresholds'=>[...]]
     * @return array новый content для события m.room.power_levels
     */
    public static function applyChange(array $levels, array $change): array {
        $next = $levels;

        if (isset($change['user_id'], $change['level'])) {
            $userId = (string) $change['user_id'];
            $level = (int) $change['level'];
            if ($level === (int) ($next['users_default'] ?? 0)) {
                unset($next['users'][$userId]);
            } else {
                $next['users'][$userId] = $level;
            }
        }

        if (isset($change['thresholds']) && is_array($change['thresholds'])) {
            foreach ($change['thresholds'] as $key => $value) {
                if (in_array($key, self::THRESHOLD_KEYS, true) && is_numeric($value)) {
                    $next[$key] = (int) $value;
                }
            }
        }

        return $next;
    }
}
