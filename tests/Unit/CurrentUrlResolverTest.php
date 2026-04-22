<?php

declare(strict_types=1);

namespace HabitTracker\Tests\Unit;

use HabitTracker\Frontend\CurrentUrlResolver;
use RuntimeException;
use Throwable;

final class CurrentUrlResolverTest
{
    public static function run(): array
    {
        $tests = [
            'testResolveReturnsFallbackWhenRequestUriIsMissing',
            'testResolveKeepsSitePathForSubdirectoryInstall',
            'testResolveReturnsHomeRootWhenRequestMatchesInstallPath',
            'testResolveReturnsFallbackWhenRequestPathIsEmpty',
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

    private static function testResolveReturnsFallbackWhenRequestUriIsMissing(): void
    {
        $had_request_uri = array_key_exists('REQUEST_URI', $_SERVER);
        $original_request_uri = $had_request_uri ? (string) $_SERVER['REQUEST_URI'] : '';

        unset($_SERVER['REQUEST_URI']);

        try {
            $actual = CurrentUrlResolver::resolve('https://example.test/fallback');
        } finally {
            if ($had_request_uri) {
                $_SERVER['REQUEST_URI'] = $original_request_uri;
            } else {
                unset($_SERVER['REQUEST_URI']);
            }
        }

        self::assertSame(
            'https://example.test/fallback',
            $actual,
            'Expected fallback URL when REQUEST_URI is not available.'
        );
    }

    private static function testResolveKeepsSitePathForSubdirectoryInstall(): void
    {
        $had_request_uri = array_key_exists('REQUEST_URI', $_SERVER);
        $original_request_uri = $had_request_uri ? (string) $_SERVER['REQUEST_URI'] : '';
        $_SERVER['REQUEST_URI'] = '/wordpress/progress/?filter=month';

        try {
            $actual = CurrentUrlResolver::resolve('https://example.test/fallback');
        } finally {
            if ($had_request_uri) {
                $_SERVER['REQUEST_URI'] = $original_request_uri;
            } else {
                unset($_SERVER['REQUEST_URI']);
            }
        }

        self::assertSame(
            'https://example.test/wordpress/progress/?filter=month',
            $actual,
            'Expected resolved URL to remain inside the WordPress subdirectory.'
        );
    }

    private static function testResolveReturnsHomeRootWhenRequestMatchesInstallPath(): void
    {
        $had_request_uri = array_key_exists('REQUEST_URI', $_SERVER);
        $original_request_uri = $had_request_uri ? (string) $_SERVER['REQUEST_URI'] : '';
        $_SERVER['REQUEST_URI'] = '/wordpress';

        try {
            $actual = CurrentUrlResolver::resolve('https://example.test/fallback');
        } finally {
            if ($had_request_uri) {
                $_SERVER['REQUEST_URI'] = $original_request_uri;
            } else {
                unset($_SERVER['REQUEST_URI']);
            }
        }

        self::assertSame(
            'https://example.test/wordpress/',
            $actual,
            'Expected home root URL when request path equals the install path.'
        );
    }

    private static function testResolveReturnsFallbackWhenRequestPathIsEmpty(): void
    {
        $had_request_uri = array_key_exists('REQUEST_URI', $_SERVER);
        $original_request_uri = $had_request_uri ? (string) $_SERVER['REQUEST_URI'] : '';
        $_SERVER['REQUEST_URI'] = 'https://example.test?only=query';

        try {
            $actual = CurrentUrlResolver::resolve('https://example.test/fallback');
        } finally {
            if ($had_request_uri) {
                $_SERVER['REQUEST_URI'] = $original_request_uri;
            } else {
                unset($_SERVER['REQUEST_URI']);
            }
        }

        self::assertSame(
            'https://example.test/fallback',
            $actual,
            'Expected fallback URL when request URI has no usable path.'
        );
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
