<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter\Tests;

use DateTimeImmutable;
use PhilipRehberger\ExceptionReporter\ExceptionReport;
use PHPUnit\Framework\TestCase;

final class ExceptionReportTest extends TestCase
{
    public function test_from_throwable_populates_all_properties(): void
    {
        $exception = new \RuntimeException('test message');

        $report = ExceptionReport::fromThrowable($exception);

        $this->assertSame(\RuntimeException::class, $report->class);
        $this->assertSame('test message', $report->message);
        $this->assertSame(__FILE__, $report->file);
        $this->assertIsInt($report->line);
        $this->assertNotEmpty($report->trace);
        $this->assertInstanceOf(DateTimeImmutable::class, $report->timestamp);
        $this->assertSame([], $report->context);
    }

    public function test_from_throwable_without_previous_has_null_previous_fields(): void
    {
        $exception = new \RuntimeException('no previous');

        $report = ExceptionReport::fromThrowable($exception);

        $this->assertNull($report->previousClass);
        $this->assertNull($report->previousMessage);
    }

    public function test_from_throwable_with_previous_populates_previous_fields(): void
    {
        $previous = new \InvalidArgumentException('root cause');
        $exception = new \RuntimeException('wrapper', 0, $previous);

        $report = ExceptionReport::fromThrowable($exception);

        $this->assertSame(\InvalidArgumentException::class, $report->previousClass);
        $this->assertSame('root cause', $report->previousMessage);
    }

    public function test_from_throwable_with_deeply_nested_exceptions_only_captures_immediate_previous(): void
    {
        $deepest = new \LogicException('level 3');
        $middle = new \InvalidArgumentException('level 2', 0, $deepest);
        $top = new \RuntimeException('level 1', 0, $middle);

        $report = ExceptionReport::fromThrowable($top);

        $this->assertSame(\RuntimeException::class, $report->class);
        $this->assertSame('level 1', $report->message);
        $this->assertSame(\InvalidArgumentException::class, $report->previousClass);
        $this->assertSame('level 2', $report->previousMessage);
    }

    public function test_from_throwable_with_context(): void
    {
        $context = ['user_id' => 42, 'action' => 'save'];
        $exception = new \RuntimeException('context test');

        $report = ExceptionReport::fromThrowable($exception, $context);

        $this->assertSame($context, $report->context);
    }

    public function test_from_throwable_with_empty_context(): void
    {
        $exception = new \RuntimeException('empty context');

        $report = ExceptionReport::fromThrowable($exception, []);

        $this->assertSame([], $report->context);
    }

    public function test_to_array_contains_all_required_keys(): void
    {
        $report = ExceptionReport::fromThrowable(new \RuntimeException('array test'));

        $array = $report->toArray();

        $this->assertArrayHasKey('class', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('file', $array);
        $this->assertArrayHasKey('line', $array);
        $this->assertArrayHasKey('trace', $array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('context', $array);
        $this->assertCount(7, $array);
    }

    public function test_to_array_does_not_include_previous_exception_fields(): void
    {
        $previous = new \LogicException('cause');
        $exception = new \RuntimeException('effect', 0, $previous);

        $report = ExceptionReport::fromThrowable($exception);

        $array = $report->toArray();

        $this->assertArrayNotHasKey('previousClass', $array);
        $this->assertArrayNotHasKey('previousMessage', $array);
    }

    public function test_to_array_without_previous_exception(): void
    {
        $report = ExceptionReport::fromThrowable(new \RuntimeException('solo'));

        $array = $report->toArray();

        $this->assertSame(\RuntimeException::class, $array['class']);
        $this->assertSame('solo', $array['message']);
        $this->assertSame([], $array['context']);
    }

    public function test_to_array_with_context_data(): void
    {
        $context = ['request_id' => 'abc-123', 'ip' => '127.0.0.1'];
        $report = ExceptionReport::fromThrowable(new \RuntimeException('ctx'), $context);

        $array = $report->toArray();

        $this->assertSame($context, $array['context']);
    }

    public function test_to_array_timestamp_is_iso8601_string(): void
    {
        $report = ExceptionReport::fromThrowable(new \RuntimeException('time test'));

        $array = $report->toArray();

        $this->assertIsString($array['timestamp']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $array['timestamp']);
    }

    public function test_fingerprint_is_deterministic(): void
    {
        $exception = new \RuntimeException('deterministic');

        $report1 = ExceptionReport::fromThrowable($exception);
        $report2 = ExceptionReport::fromThrowable($exception);

        $this->assertSame($report1->fingerprint(), $report2->fingerprint());
    }

    public function test_fingerprint_differs_for_different_exception_classes(): void
    {
        $runtime = ExceptionReport::fromThrowable(new \RuntimeException('same message'));
        $logic = ExceptionReport::fromThrowable(new \LogicException('same message'));

        $this->assertNotSame($runtime->fingerprint(), $logic->fingerprint());
    }

    public function test_fingerprint_is_md5_hash(): void
    {
        $report = ExceptionReport::fromThrowable(new \RuntimeException('hash test'));

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $report->fingerprint());
    }

    public function test_constructor_allows_direct_instantiation(): void
    {
        $timestamp = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

        $report = new ExceptionReport(
            class: \RuntimeException::class,
            message: 'direct',
            file: '/tmp/test.php',
            line: 42,
            trace: '#0 test trace',
            timestamp: $timestamp,
            context: ['key' => 'value'],
            previousClass: \LogicException::class,
            previousMessage: 'cause',
        );

        $this->assertSame(\RuntimeException::class, $report->class);
        $this->assertSame('direct', $report->message);
        $this->assertSame('/tmp/test.php', $report->file);
        $this->assertSame(42, $report->line);
        $this->assertSame('#0 test trace', $report->trace);
        $this->assertSame($timestamp, $report->timestamp);
        $this->assertSame(['key' => 'value'], $report->context);
        $this->assertSame(\LogicException::class, $report->previousClass);
        $this->assertSame('cause', $report->previousMessage);
    }

    public function test_constructor_defaults_for_optional_fields(): void
    {
        $report = new ExceptionReport(
            class: \RuntimeException::class,
            message: 'minimal',
            file: '/tmp/test.php',
            line: 1,
            trace: '',
            timestamp: new DateTimeImmutable,
        );

        $this->assertSame([], $report->context);
        $this->assertNull($report->previousClass);
        $this->assertNull($report->previousMessage);
    }

    public function test_from_throwable_with_empty_message(): void
    {
        $exception = new \RuntimeException('');

        $report = ExceptionReport::fromThrowable($exception);

        $this->assertSame('', $report->message);
        $this->assertSame('', $report->toArray()['message']);
    }

    public function test_nested_exception_chain_of_three_levels(): void
    {
        $level3 = new \OverflowException('overflow');
        $level2 = new \LogicException('logic fail', 0, $level3);
        $level1 = new \RuntimeException('runtime fail', 0, $level2);

        $report = ExceptionReport::fromThrowable($level1);

        // Only the immediate previous is captured
        $this->assertSame(\RuntimeException::class, $report->class);
        $this->assertSame(\LogicException::class, $report->previousClass);
        $this->assertSame('logic fail', $report->previousMessage);

        // Verify the middle exception also has its own previous
        $middleReport = ExceptionReport::fromThrowable($level2);
        $this->assertSame(\LogicException::class, $middleReport->class);
        $this->assertSame(\OverflowException::class, $middleReport->previousClass);
        $this->assertSame('overflow', $middleReport->previousMessage);
    }
}
