<?php

declare(strict_types=1);

namespace HabitTracker\Tests\Unit;

use HabitTracker\Domain\Analytics\HabitMath;
use RuntimeException;
use Throwable;

final class HabitMathTest
{
    public static function run(): array
    {
        $tests = [
            'testDateSetStreakKeepsYesterdayStreakUntilTodayIsChecked',
            'testDateSetStreakResetsWhenYesterdayIsMissed',
            'testDailyHabitStreakSupportsGraceUntilTodayIsChecked',
            'testWeeklyHabitStreakKeepsPreviousRunWhenCurrentWeekNotComplete',
            'testWeeklyHabitStreakIncludesCurrentWeekWhenTargetIsReached',
            'testDailyTargetForPeriodCountsEveryElapsedDay',
            'testWeeklyTargetForPeriodCountsDistinctEligibleWeeks',
            'testWeeklyTargetForPeriodCapsByAvailableDaysInPartialWeeks',
            'testDateRangeIsStableAcrossDstChange',
            'testDateRangeReturnsEmptyOnInvalidRange',
        ];

        $failures = [];
        $count = 0;

        foreach ($tests as $method) {
            $count++;

            try {
                self::$method();
            } catch (Throwable $exception) {
                $failures[] = sprintf('%s: %s', $method, $exception->getMessage());
            }
        }

        return [
            'count' => $count,
            'failures' => $failures,
        ];
    }

    private static function testDateSetStreakKeepsYesterdayStreakUntilTodayIsChecked(): void
    {
        $date_set = [
            '2026-03-22' => true,
            '2026-03-23' => true,
            '2026-03-24' => true,
        ];

        $actual = HabitMath::calculateDateSetStreak($date_set, '2026-03-25', 365);

        self::assertSame(3, $actual, 'Expected streak from previous days to remain visible before today check-in.');
    }

    private static function testDateSetStreakResetsWhenYesterdayIsMissed(): void
    {
        $date_set = [
            '2026-03-22' => true,
            '2026-03-23' => true,
        ];

        $actual = HabitMath::calculateDateSetStreak($date_set, '2026-03-25', 365);

        self::assertSame(0, $actual, 'Expected streak reset when previous day has no completion.');
    }

    private static function testDailyHabitStreakSupportsGraceUntilTodayIsChecked(): void
    {
        $habit_history = [
            '2026-03-23' => true,
            '2026-03-24' => true,
        ];

        $actual = HabitMath::calculateDailyHabitStreak($habit_history, '2026-03-25', 30);

        self::assertSame(2, $actual, 'Expected daily streak grace on enabled current day.');
    }

    private static function testWeeklyHabitStreakKeepsPreviousRunWhenCurrentWeekNotComplete(): void
    {
        $habit_history = [
            '2026-03-24' => true, // current week: 1 completion
            '2026-03-16' => true,
            '2026-03-17' => true,
            '2026-03-18' => true, // previous week: 3 completions
            '2026-03-09' => true,
            '2026-03-10' => true,
            '2026-03-11' => true, // two weeks ago: 3 completions
        ];

        $actual = HabitMath::calculateWeeklyHabitStreak($habit_history, '2026-03-25', 3, 60);

        self::assertSame(2, $actual, 'Expected weekly streak grace until the current week target is reached.');
    }

    private static function testWeeklyHabitStreakIncludesCurrentWeekWhenTargetIsReached(): void
    {
        $habit_history = [
            '2026-03-24' => true,
            '2026-03-25' => true,
            '2026-03-26' => true, // current week: 3 completions
            '2026-03-16' => true,
            '2026-03-17' => true,
            '2026-03-18' => true,
            '2026-03-09' => true,
            '2026-03-10' => true,
            '2026-03-11' => true,
        ];

        $actual = HabitMath::calculateWeeklyHabitStreak($habit_history, '2026-03-26', 3, 60);

        self::assertSame(3, $actual, 'Expected current week to count when weekly target is met.');
    }

    private static function testDailyTargetForPeriodCountsEveryElapsedDay(): void
    {
        $actual = HabitMath::calculateTargetForPeriod(
            HabitMath::FREQUENCY_DAILY,
            1,
            '2026-03-23',
            '2026-03-29'
        );

        self::assertSame(7, $actual, 'Expected daily targets for each elapsed day in range.');
    }

    private static function testWeeklyTargetForPeriodCountsDistinctEligibleWeeks(): void
    {
        $actual = HabitMath::calculateTargetForPeriod(
            HabitMath::FREQUENCY_WEEKLY,
            3,
            '2026-03-23',
            '2026-04-05'
        );

        self::assertSame(6, $actual, 'Expected target count multiplied by distinct weeks in range.');
    }

    private static function testWeeklyTargetForPeriodCapsByAvailableDaysInPartialWeeks(): void
    {
        $actual = HabitMath::calculateTargetForPeriod(
            HabitMath::FREQUENCY_WEEKLY,
            7,
            '2026-04-01',
            '2026-04-30'
        );

        self::assertSame(30, $actual, 'Expected weekly target to be capped by available month days in partial weeks.');
    }

    private static function testDateRangeIsStableAcrossDstChange(): void
    {
        $previous_timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Sofia');

        try {
            $actual = HabitMath::buildDateRange('2026-03-28', '2026-03-31');
        } finally {
            date_default_timezone_set($previous_timezone);
        }

        self::assertSame(
            ['2026-03-28', '2026-03-29', '2026-03-30', '2026-03-31'],
            $actual,
            'Expected date range generation to be stable across DST shift.'
        );
    }

    private static function testDateRangeReturnsEmptyOnInvalidRange(): void
    {
        $actual = HabitMath::buildDateRange('2026-04-02', '2026-04-01');

        self::assertSame([], $actual, 'Expected empty range when end date is before start date.');
    }

    private static function assertSame($expected, $actual, string $message): void
    {
        if ($expected === $actual) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                "%s\nExpected: %s\nActual:   %s",
                $message,
                var_export($expected, true),
                var_export($actual, true)
            )
        );
    }
}
