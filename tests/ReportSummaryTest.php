<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter\Tests;

use DateTimeImmutable;
use PhilipRehberger\ExceptionReporter\ExceptionReport;
use PhilipRehberger\ExceptionReporter\ReportSummary;
use PHPUnit\Framework\TestCase;

final class ReportSummaryTest extends TestCase
{
    public function test_total_count(): void
    {
        $reports = [
            ExceptionReport::fromThrowable(new \RuntimeException('one')),
            ExceptionReport::fromThrowable(new \RuntimeException('two')),
            ExceptionReport::fromThrowable(new \LogicException('three')),
        ];

        $summary = new ReportSummary($reports);

        $this->assertSame(3, $summary->totalCount());
    }

    public function test_unique_count_with_duplicate_fingerprints(): void
    {
        $exception = new \RuntimeException('same');

        $reports = [
            ExceptionReport::fromThrowable($exception),
            ExceptionReport::fromThrowable($exception),
            ExceptionReport::fromThrowable(new \LogicException('different')),
        ];

        $summary = new ReportSummary($reports);

        // RuntimeException from same file+line = 1 fingerprint, LogicException = another
        $this->assertSame(2, $summary->uniqueCount());
    }

    public function test_top_exceptions_returns_sorted_by_frequency(): void
    {
        $runtime = new \RuntimeException('runtime error');
        $logic = new \LogicException('logic error');

        $reports = [
            ExceptionReport::fromThrowable($runtime),
            ExceptionReport::fromThrowable($logic),
            ExceptionReport::fromThrowable($runtime),
            ExceptionReport::fromThrowable($runtime),
            ExceptionReport::fromThrowable($logic),
        ];

        $summary = new ReportSummary($reports);
        $top = $summary->topExceptions(2);

        $this->assertCount(2, $top);
        $this->assertSame('runtime error', $top[0]['message']);
        $this->assertSame(3, $top[0]['count']);
        $this->assertSame('logic error', $top[1]['message']);
        $this->assertSame(2, $top[1]['count']);
    }

    public function test_top_exceptions_includes_last_seen(): void
    {
        $reports = [
            ExceptionReport::fromThrowable(new \RuntimeException('test')),
        ];

        $summary = new ReportSummary($reports);
        $top = $summary->topExceptions();

        $this->assertArrayHasKey('lastSeen', $top[0]);
        $this->assertInstanceOf(DateTimeImmutable::class, $top[0]['lastSeen']);
    }

    public function test_top_exceptions_respects_limit(): void
    {
        $reports = [
            ExceptionReport::fromThrowable(new \RuntimeException('a')),
            ExceptionReport::fromThrowable(new \LogicException('b')),
            ExceptionReport::fromThrowable(new \InvalidArgumentException('c')),
        ];

        $summary = new ReportSummary($reports);
        $top = $summary->topExceptions(2);

        $this->assertCount(2, $top);
    }

    public function test_since_returns_earliest_timestamp(): void
    {
        $early = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $late = new DateTimeImmutable('2026-06-15T12:00:00+00:00');

        $reports = [
            new ExceptionReport(
                class: \RuntimeException::class,
                message: 'late',
                file: '/tmp/test.php',
                line: 10,
                trace: '',
                timestamp: $late,
            ),
            new ExceptionReport(
                class: \RuntimeException::class,
                message: 'early',
                file: '/tmp/test.php',
                line: 20,
                trace: '',
                timestamp: $early,
            ),
        ];

        $summary = new ReportSummary($reports);

        $this->assertSame($early, $summary->since());
    }

    public function test_until_returns_latest_timestamp(): void
    {
        $early = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $late = new DateTimeImmutable('2026-06-15T12:00:00+00:00');

        $reports = [
            new ExceptionReport(
                class: \RuntimeException::class,
                message: 'early',
                file: '/tmp/test.php',
                line: 10,
                trace: '',
                timestamp: $early,
            ),
            new ExceptionReport(
                class: \RuntimeException::class,
                message: 'late',
                file: '/tmp/test.php',
                line: 20,
                trace: '',
                timestamp: $late,
            ),
        ];

        $summary = new ReportSummary($reports);

        $this->assertSame($late, $summary->until());
    }

    public function test_empty_summary(): void
    {
        $summary = new ReportSummary([]);

        $this->assertSame(0, $summary->totalCount());
        $this->assertSame(0, $summary->uniqueCount());
        $this->assertSame([], $summary->topExceptions());
        $this->assertNull($summary->since());
        $this->assertNull($summary->until());
    }
}
