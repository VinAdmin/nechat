<?php

namespace Tests\Unit\Models;

use app\models\Users;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Юнит-тесты Users::displayName() и обработки поля `name` в Users::updateProfile().
 * Требуют реально настроенной БД (config/db.php) — модели проекта подключаются к БД
 * напрямую. Тестовые пользователи заводятся напрямую через insert() (как это делает
 * Users::registration(), который читает только php://input и недоступен в CLI) и
 * удаляются в tearDown().
 */
#[TestDox('Отображаемое имя пользователя (Users::displayName / updateProfile name)')]
final class UsersDisplayNameTest extends TestCase
{
    private string $suffix;
    private string $userId;

    protected function setUp(): void
    {
        $this->suffix = bin2hex(random_bytes(6));
        $this->userId = '@phpunit_name_' . $this->suffix . ':' . \wco\kernel\WCO::$domain;

        (new Users())->insert([
            'user_id'  => $this->userId,
            'password' => password_hash('TestPass123!', PASSWORD_BCRYPT),
            'cdate'    => time(),
        ]);
    }

    protected function tearDown(): void
    {
        (new Users())->delete('user_id = :user_id')->execute([':user_id' => $this->userId]);
    }

    #[TestDox('Если name заполнено — displayName() возвращает name')]
    public function testDisplayNameReturnsNameWhenSet(): void
    {
        (new Users())->Update(['name' => 'Алиса', 'user_id' => $this->userId], 'user_id = :user_id');

        self::assertSame('Алиса', (new Users())->displayName($this->userId));
    }

    #[TestDox('Если name пустая строка — displayName() возвращает user_id')]
    public function testDisplayNameFallsBackToUserIdWhenEmpty(): void
    {
        (new Users())->Update(['name' => '', 'user_id' => $this->userId], 'user_id = :user_id');

        self::assertSame($this->userId, (new Users())->displayName($this->userId));
    }

    #[TestDox('Если name = NULL — displayName() возвращает user_id')]
    public function testDisplayNameFallsBackToUserIdWhenNull(): void
    {
        self::assertSame($this->userId, (new Users())->displayName($this->userId));
    }

    #[TestDox('Для несуществующего пользователя displayName() возвращает переданный user_id')]
    public function testDisplayNameReturnsUserIdForUnknownUser(): void
    {
        $unknown = '@phpunit_missing_' . $this->suffix . ':' . \wco\kernel\WCO::$domain;

        self::assertSame($unknown, (new Users())->displayName($unknown));
    }

    #[TestDox('updateProfile() сохраняет name с обрезкой тегов и длины до 255 символов')]
    public function testUpdateProfileStoresSanitizedName(): void
    {
        (new Users())->updateProfile($this->userId, ['name' => '  <b>Боб</b> ' . str_repeat('я', 300)]);

        $stored = (new Users())->getUserById($this->userId)['name'];

        self::assertStringNotContainsString('<b>', $stored);
        self::assertLessThanOrEqual(255, mb_strlen($stored));
        self::assertStringContainsString('Боб', $stored);
    }

    #[TestDox('updateProfile() позволяет очистить name пустой строкой')]
    public function testUpdateProfileClearsName(): void
    {
        (new Users())->Update(['name' => 'Временное', 'user_id' => $this->userId], 'user_id = :user_id');

        (new Users())->updateProfile($this->userId, ['name' => '']);

        self::assertSame($this->userId, (new Users())->displayName($this->userId));
    }
}
