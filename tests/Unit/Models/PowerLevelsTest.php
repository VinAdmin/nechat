<?php

namespace Tests\Unit\Models;

use app\models\PowerLevels;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[TestDox('Чистый помощник PowerLevels: дефолты, парсинг, уровни, пороги, правила')]
final class PowerLevelsTest extends TestCase
{
    #[TestDox('parse(null) возвращает дефолты')]
    public function testParseNullReturnsDefaults(): void
    {
        $levels = PowerLevels::parse(null);
        self::assertSame(0, $levels['events_default']);
        self::assertSame(50, $levels['ban']);
        self::assertSame(50, $levels['redact']);
        self::assertSame(100, $levels['power_levels']);
        self::assertSame([], $levels['users']);
    }

    #[TestDox('parse() мерджит content с дефолтами и приводит типы')]
    public function testParseMergesAndCastsTypes(): void
    {
        $levels = PowerLevels::parse([
            'events_default' => '50',
            'ban' => 75,
            'users' => ['@a:d' => '100', '@b:d' => 50, 'bad' => 'x', 123 => 10],
        ]);
        self::assertSame(50, $levels['events_default']);
        self::assertSame(75, $levels['ban']);
        self::assertSame(50, $levels['redact'], 'непереданный порог остаётся дефолтным');
        self::assertSame(['@a:d' => 100, '@b:d' => 50], $levels['users'], 'нечисловые/нестроковые ключи отброшены');
    }

    #[TestDox('levelForUser: users > users_default; создатель всегда >= 100')]
    public function testLevelForUser(): void
    {
        $levels = PowerLevels::parse(['users_default' => 5, 'users' => ['@mod:d' => 50, '@owner:d' => 30]]);
        self::assertSame(50, PowerLevels::levelForUser($levels, '@mod:d', '@owner:d'));
        self::assertSame(5, PowerLevels::levelForUser($levels, '@nobody:d', '@owner:d'));
        self::assertSame(100, PowerLevels::levelForUser($levels, '@owner:d', '@owner:d'), 'создатель ниже 100 в users всё равно 100');
    }

    #[TestDox('threshold: возвращает порог действия, unban == ban')]
    public function testThreshold(): void
    {
        $levels = PowerLevels::parse(['ban' => 60, 'kick' => 40]);
        self::assertSame(60, PowerLevels::threshold($levels, 'ban'));
        self::assertSame(60, PowerLevels::threshold($levels, 'unban'));
        self::assertSame(40, PowerLevels::threshold($levels, 'kick'));
        self::assertSame(0, PowerLevels::threshold($levels, 'events_default'));
    }

    #[TestDox('canAssignLevel: нельзя выше своего; равный своему — можно')]
    public function testCanAssignLevelUpperBound(): void
    {
        self::assertFalse(PowerLevels::canAssignLevel(50, '@me:d', '@t:d', 0, 60)['ok']);
        self::assertTrue(PowerLevels::canAssignLevel(100, '@me:d', '@t:d', 0, 100)['ok']);
        self::assertTrue(PowerLevels::canAssignLevel(50, '@me:d', '@t:d', 0, 50)['ok']);
    }

    #[TestDox('canAssignLevel: нельзя трогать участника с уровнем >= своего (кроме себя)')]
    public function testCanAssignLevelPeerProtection(): void
    {
        self::assertFalse(PowerLevels::canAssignLevel(50, '@me:d', '@peer:d', 50, 10)['ok']);
        self::assertTrue(PowerLevels::canAssignLevel(50, '@me:d', '@me:d', 50, 10)['ok'], 'себя понижать можно');
    }

    #[TestDox('canModifyThresholds: нельзя поднять порог выше своего уровня')]
    public function testCanModifyThresholds(): void
    {
        self::assertFalse(PowerLevels::canModifyThresholds(50, ['ban' => 75])['ok']);
        self::assertTrue(PowerLevels::canModifyThresholds(50, ['ban' => 50, 'kick' => 30])['ok']);
    }

    #[TestDox('applyChange: level == users_default удаляет пользователя из users')]
    public function testApplyChangeRemovesAtDefault(): void
    {
        $levels = PowerLevels::parse(['users_default' => 0, 'users' => ['@a:d' => 50]]);
        $next = PowerLevels::applyChange($levels, ['user_id' => '@a:d', 'level' => 0]);
        self::assertArrayNotHasKey('@a:d', $next['users']);
    }

    #[TestDox('applyChange: назначение уровня и изменение порогов')]
    public function testApplyChangeSetsValues(): void
    {
        $levels = PowerLevels::parse(null);
        $next = PowerLevels::applyChange($levels, ['user_id' => '@a:d', 'level' => 50]);
        self::assertSame(50, $next['users']['@a:d']);
        $next2 = PowerLevels::applyChange($levels, ['thresholds' => ['events_default' => 50, 'bogus' => 1]]);
        self::assertSame(50, $next2['events_default']);
        self::assertArrayNotHasKey('bogus', $next2);
    }
}
