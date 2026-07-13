<?php

namespace app\models;

use wco\db\DB;

/**
 * Модель присутствия пользователей (user_presence).
 * 
 * Отслеживает онлайн-статус пользователей через heartbeat.
 * 
 * @author Olkhin Vitaliy <ovvitalik@gmail.com>
 * @copyright (c) 2026, Olkhin Vitaliy
 */
class UserPresence extends DB {
    const TIMEOUT = 60;

    function __construct() {
        parent::__construct();
    }

    public function init() {
        return 'user_presence';
    }

    /**
     * Обновляет время последней активности пользователя.
     */
    public function heartbeat(string $userId): void {
        $this->select()->from()->where("user_id = :user_id");
        $existing = $this->fetch(['user_id' => $userId]);

        if (isset($existing['user_id'])) {
            $this->Update([
                'user_id'     => $userId,
                'last_active' => time(),
                'status'      => 'online'
            ], 'user_id = :user_id');
        } else {
            $this->insert([
                'user_id'     => $userId,
                'last_active' => time(),
                'status'      => 'online'
            ]);
        }
    }

    /**
     * Устанавливает кастомный статус.
     */
    public function setStatus(string $userId, string $status): void {
        $this->Update([
            'user_id'     => $userId,
            'last_active' => time(),
            'status'      => $status
        ], 'user_id = :user_id');
    }

    /**
     * Возвращает список онлайн-пользователей (активных за последние TIMEOUT секунд).
     */
    public function getOnlineUsers(): array {
        $threshold = time() - self::TIMEOUT;
        $this->select()->from()->where("last_active > :threshold AND status != 'invisible'");
        return $this->fetchAll(['threshold' => $threshold]);
    }

    /**
     * Возвращает статус конкретного пользователя.
     */
    public function getPresence(string $userId): array {
        $threshold = time() - self::TIMEOUT;
        $this->select()->from()->where("user_id = :user_id");
        $result = $this->fetch(['user_id' => $userId]);

        if (!isset($result['user_id'])) {
            return ['user_id' => $userId, 'status' => 'offline', 'last_active' => 0];
        }

        $result['status'] = ($result['last_active'] > $threshold) ? ($result['status'] ?: 'online') : 'offline';
        return $result;
    }

    /**
     * Возвращает статусы нескольких пользователей.
     */
    public function getPresences(array $userIds): array {
        if (empty($userIds)) return [];

        $threshold = time() - self::TIMEOUT;
        $placeholders = implode(',', array_fill(0, count($userIds), ':uid' . md5($uniq = uniqid())));
        $params = [];
        foreach ($userIds as $i => $id) {
            $params[':uid' . md5($uniq . $i)] = $id;
        }

        $sql = "SELECT user_id, last_active, status FROM {$this->init()} WHERE user_id IN ($placeholders)";
        self::setAssembly($sql);
        $results = $this->fetchAll($params);

        $map = [];
        foreach ($results as $row) {
            $row['status'] = ($row['last_active'] > $threshold) ? ($row['status'] ?: 'online') : 'offline';
            $map[$row['user_id']] = $row;
        }

        foreach ($userIds as $id) {
            if (!isset($map[$id])) {
                $map[$id] = ['user_id' => $id, 'status' => 'offline', 'last_active' => 0];
            }
        }

        return $map;
    }
}
