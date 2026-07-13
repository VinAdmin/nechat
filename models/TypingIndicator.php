<?php

namespace app\models;

use wco\db\DB;

/**
 * Модель индикаторов набора текста (typing_indicators).
 * 
 * Хранит кто сейчас печатает в какой комнате.
 * Записи автоматически истекают через 5 секунд.
 * 
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class TypingIndicator extends DB {
    const EXPIRE = 5;

    function __construct() {
        parent::__construct();
    }

    public function init() {
        return 'typing_indicators';
    }

    /**
     * Устанавливает/обновляет индикатор набора.
     */
    public function setTyping(string $userId, string $roomId): void {
        $this->select()->from()->where("user_id = :user_id AND room_id = :room_id");
        $existing = $this->fetch(['user_id' => $userId, 'room_id' => $roomId]);

        if (isset($existing['user_id'])) {
            $this->Update([
                'user_id'    => $userId,
                'room_id'    => $roomId,
                'typed_at'   => time()
            ], 'user_id = :user_id AND room_id = :room_id');
        } else {
            $this->insert([
                'user_id'    => $userId,
                'room_id'    => $roomId,
                'typed_at'   => time()
            ]);
        }
    }

    /**
     * Убирает индикатор набора.
     */
    public function stopTyping(string $userId, string $roomId): void {
        $this->delete("user_id = :user_id AND room_id = :room_id")
            ->execute([':user_id' => $userId, ':room_id' => $roomId]);
    }

    /**
     * Возвращает список печатающих в комнате (кроме указанного пользователя).
     */
    public function getTypingUsers(string $roomId, string $exceptUserId = ''): array {
        $threshold = time() - self::EXPIRE;

        $this->select()->from()
            ->where("room_id = :room_id AND typed_at > :threshold AND user_id != :user_id");

        $results = $this->fetchAll([
            'room_id'   => $roomId,
            'threshold' => $threshold,
            'user_id'   => $exceptUserId
        ]);

        return array_map(fn($r) => $r['user_id'], $results);
    }

    /**
     * Очищает устаревшие записи.
     */
    public function cleanup(): void {
        $threshold = time() - self::EXPIRE * 2;
        $this->delete("typed_at < :threshold")
            ->execute([':threshold' => $threshold]);
    }
}
