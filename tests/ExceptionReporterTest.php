<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter\Tests;

use PhilipRehberger\ExceptionReporter\Channels\CallbackChannel;
use PhilipRehberger\ExceptionReporter\Channels\FileChannel;
use PhilipRehberger\ExceptionReporter\Contracts\ReportChannel;
use PhilipRehberger\ExceptionReporter\ExceptionReport;
use PhilipRehberger\ExceptionReporter\ExceptionReporter;
use PHPUnit\Framework\TestCase;

final class ExceptionReporterTest extends TestCase
{
    public function test_capture_creates_exception_report(): void
    {
        $reporter = new ExceptionReporter;
        $exception = new \RuntimeException('Something went wrong');

        $report = $reporter->capture($exception);

        $this->assertInstanceOf(ExceptionReport::class, $report);
        $this->assertSame(\RuntimeException::class, $report->class);
        $this->assertSame('Something went wrong', $report->message);
    }

    public function test_report_sent_to_all_channels(): void
    {
        $received = [];

        $channel1 = new CallbackChannel(function (ExceptionReport $report) use (&$received): void {
            $received[] = 'channel1';
        });

        $channel2 = new CallbackChannel(function (ExceptionReport $report) use (&$received): void {
            $received[] = 'channel2';
        });

        $reporter = new ExceptionReporter;
        $reporter->addChannel($channel1)->addChannel($channel2);
        $reporter->capture(new \RuntimeException('test'));

        $this->assertSame(['channel1', 'channel2'], $received);
    }

    public function test_report_includes_context(): void
    {
        $reporter = new ExceptionReporter;
        $context = ['user_id' => 42, 'action' => 'checkout'];

        $report = $reporter->capture(new \RuntimeException('fail'), $context);

        $this->assertSame($context, $report->context);
    }

    public function test_fingerprint_deduplication_prevents_duplicate_reports(): void
    {
        $count = 0;
        $channel = new CallbackChannel(function () use (&$count): void {
            $count++;
        });

        $reporter = new ExceptionReporter;
        $reporter->addChannel($channel)->enableDeduplication();

        $exception = new \RuntimeException('duplicate');
        $reporter->capture($exception);
        $reporter->capture($exception);
        $reporter->capture($exception);

        $this->assertSame(1, $count);
    }

    public function test_deduplication_different_exceptions_not_deduplicated(): void
    {
        $count = 0;
        $channel = new CallbackChannel(function () use (&$count): void {
            $count++;
        });

        $reporter = new ExceptionReporter;
        $reporter->addChannel($channel)->enableDeduplication();

        $reporter->capture(new \RuntimeException('first'));
        $reporter->capture(new \LogicException('second'));

        $this->assertSame(2, $count);
    }

    public function test_reset_fingerprints_allows_re_reporting(): void
    {
        $count = 0;
        $channel = new CallbackChannel(function () use (&$count): void {
            $count++;
        });

        $reporter = new ExceptionReporter;
        $reporter->addChannel($channel)->enableDeduplication();

        $exception = new \RuntimeException('again');
        $reporter->capture($exception);
        $reporter->resetFingerprints();
        $reporter->capture($exception);

        $this->assertSame(2, $count);
    }

    public function test_channel_failure_does_not_throw(): void
    {
        $failingChannel = new class implements ReportChannel
        {
            public function report(ExceptionReport $report): void
            {
                throw new \RuntimeException('Channel broke');
            }
        };

        $reported = false;
        $safeChannel = new CallbackChannel(function () use (&$reported): void {
            $reported = true;
        });

        $reporter = new ExceptionReporter;
        $reporter->addChannel($failingChannel)->addChannel($safeChannel);

        $report = $reporter->capture(new \RuntimeException('test'));

        $this->assertInstanceOf(ExceptionReport::class, $report);
        $this->assertTrue($reported);
    }

    public function test_file_channel_writes_json_line(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'exception_reporter_test_');
        $this->assertNotFalse($tmpFile);

        try {
            $channel = new FileChannel($tmpFile);
            $reporter = new ExceptionReporter;
            $reporter->addChannel($channel);

            $reporter->capture(new \RuntimeException('file test'));

            $contents = file_get_contents($tmpFile);
            $this->assertNotFalse($contents);
            $this->assertNotEmpty(trim($contents));

            $decoded = json_decode(trim($contents), true);
            $this->assertIsArray($decoded);
            $this->assertSame(\RuntimeException::class, $decoded['class']);
            $this->assertSame('file test', $decoded['message']);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_exception_report_to_array(): void
    {
        $report = ExceptionReport::fromThrowable(
            new \InvalidArgumentException('bad input'),
            ['key' => 'value'],
        );

        $array = $report->toArray();

        $this->assertSame(\InvalidArgumentException::class, $array['class']);
        $this->assertSame('bad input', $array['message']);
        $this->assertArrayHasKey('file', $array);
        $this->assertArrayHasKey('line', $array);
        $this->assertArrayHasKey('trace', $array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertSame(['key' => 'value'], $array['context']);
    }

    public function test_exception_report_from_throwable_with_previous(): void
    {
        $previous = new \LogicException('root cause');
        $exception = new \RuntimeException('wrapper', 0, $previous);

        $report = ExceptionReport::fromThrowable($exception);

        $this->assertSame(\RuntimeException::class, $report->class);
        $this->assertSame(\LogicException::class, $report->previousClass);
        $this->assertSame('root cause', $report->previousMessage);
    }

    public function test_with_context_includes_persistent_context_in_reports(): void
    {
        $reports = [];
        $channel = new CallbackChannel(function (ExceptionReport $report) use (&$reports): void {
            $reports[] = $report;
        });

        $reporter = (new ExceptionReporter)
            ->addChannel($channel)
            ->withContext(['request_id' => 'abc-123', 'user_id' => 42]);

        $reporter->capture(new \RuntimeException('first'));
        $reporter->capture(new \LogicException('second'));

        $this->assertCount(2, $reports);
        $this->assertSame('abc-123', $reports[0]->context['request_id']);
        $this->assertSame(42, $reports[0]->context['user_id']);
        $this->assertSame('abc-123', $reports[1]->context['request_id']);
        $this->assertSame(42, $reports[1]->context['user_id']);
    }

    public function test_with_context_merges_with_per_call_context(): void
    {
        $captured = null;
        $channel = new CallbackChannel(function (ExceptionReport $report) use (&$captured): void {
            $captured = $report;
        });

        $reporter = (new ExceptionReporter)
            ->addChannel($channel)
            ->withContext(['request_id' => 'abc-123']);

        $reporter->capture(new \RuntimeException('test'), ['action' => 'checkout']);

        $this->assertSame('abc-123', $captured->context['request_id']);
        $this->assertSame('checkout', $captured->context['action']);
    }

    public function test_with_context_returns_new_instance(): void
    {
        $original = new ExceptionReporter;
        $withCtx = $original->withContext(['key' => 'value']);

        $this->assertNotSame($original, $withCtx);
    }

    public function test_with_context_does_not_mutate_original(): void
    {
        $originalReports = [];
        $originalChannel = new CallbackChannel(function (ExceptionReport $report) use (&$originalReports): void {
            $originalReports[] = $report;
        });

        $original = (new ExceptionReporter)->addChannel($originalChannel);
        $original->withContext(['request_id' => 'abc-123']);

        $original->capture(new \RuntimeException('test'));

        $this->assertSame([], $originalReports[0]->context);
    }

    public function test_set_filter_skips_filtered_exceptions(): void
    {
        $count = 0;
        $channel = new CallbackChannel(function () use (&$count): void {
            $count++;
        });

        $reporter = new ExceptionReporter;
        $reporter->addChannel($channel);
        $reporter->setFilter(fn (\Throwable $e): bool => ! $e instanceof \RuntimeException);

        $reporter->capture(new \RuntimeException('filtered'));
        $reporter->capture(new \RuntimeException('also filtered'));

        $this->assertSame(0, $count);
    }

    public function test_set_filter_passes_non_filtered_exceptions(): void
    {
        $count = 0;
        $channel = new CallbackChannel(function () use (&$count): void {
            $count++;
        });

        $reporter = new ExceptionReporter;
        $reporter->addChannel($channel);
        $reporter->setFilter(fn (\Throwable $e): bool => ! $e instanceof \RuntimeException);

        $reporter->capture(new \LogicException('allowed'));
        $reporter->capture(new \InvalidArgumentException('also allowed'));

        $this->assertSame(2, $count);
    }

    public function test_set_filter_mixed_exceptions(): void
    {
        $reported = [];
        $channel = new CallbackChannel(function (ExceptionReport $report) use (&$reported): void {
            $reported[] = $report->class;
        });

        $reporter = new ExceptionReporter;
        $reporter->addChannel($channel);
        $reporter->setFilter(fn (\Throwable $e): bool => ! $e instanceof \RuntimeException);

        $reporter->capture(new \RuntimeException('skip'));
        $reporter->capture(new \LogicException('keep'));
        $reporter->capture(new \RuntimeException('skip again'));
        $reporter->capture(new \InvalidArgumentException('keep too'));

        $this->assertSame([\LogicException::class, \InvalidArgumentException::class], $reported);
    }

    public function test_count_starts_at_zero(): void
    {
        $reporter = new ExceptionReporter;

        $this->assertSame(0, $reporter->count());
    }

    public function test_count_increments_on_capture(): void
    {
        $reporter = new ExceptionReporter;

        $reporter->capture(new \RuntimeException('one'));
        $reporter->capture(new \LogicException('two'));
        $reporter->capture(new \InvalidArgumentException('three'));

        $this->assertSame(3, $reporter->count());
    }

    public function test_count_does_not_increment_for_filtered_exceptions(): void
    {
        $reporter = new ExceptionReporter;
        $reporter->setFilter(fn (\Throwable $e): bool => ! $e instanceof \RuntimeException);

        $reporter->capture(new \RuntimeException('filtered'));
        $reporter->capture(new \LogicException('reported'));

        $this->assertSame(1, $reporter->count());
    }

    public function test_count_does_not_increment_for_deduplicated_exceptions(): void
    {
        $reporter = new ExceptionReporter;
        $reporter->enableDeduplication();

        $exception = new \RuntimeException('dedup');
        $reporter->capture($exception);
        $reporter->capture($exception);
        $reporter->capture($exception);

        $this->assertSame(1, $reporter->count());
    }
}
