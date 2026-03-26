<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Unit/HabitMathTest.php';

$suites = [
    \HabitTracker\Tests\Unit\HabitMathTest::class,
];

$total = 0;
$failures = [];

foreach ($suites as $suite) {
    if (! is_callable([$suite, 'run'])) {
        $failures[] = sprintf('%s: missing static run() method.', $suite);
        continue;
    }

    $result = $suite::run();
    $suite_count = isset($result['count']) ? (int) $result['count'] : 0;
    $suite_failures = isset($result['failures']) && is_array($result['failures'])
        ? $result['failures']
        : [];

    $total += $suite_count;

    foreach ($suite_failures as $failure) {
        $failures[] = sprintf('%s -> %s', $suite, (string) $failure);
    }
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Failed tests: %d/%d\n", count($failures), $total));

    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . PHP_EOL);
    }

    exit(1);
}

fwrite(STDOUT, sprintf("All tests passed: %d\n", $total));

