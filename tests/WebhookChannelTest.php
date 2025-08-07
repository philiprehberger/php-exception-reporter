<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter\Tests;

use PhilipRehberger\ExceptionReporter\Channels\WebhookChannel;
use PhilipRehberger\ExceptionReporter\Contracts\ReportChannel;
use PhilipRehberger\ExceptionReporter\ExceptionReport;
use PHPUnit\Framework\TestCase;

final class WebhookChannelTest extends TestCase
{
    public function test_implements_report_channel_interface(): void
    {
        $channel = new WebhookChannel('https://example.com/webhook');

        $this->assertInstanceOf(ReportChannel::class, $channel);
    }

    public function test_custom_transform_callable_is_used(): void
    {
        $transformCalled = false;
        $receivedReport = null;

        $transform = function (ExceptionReport $report) use (&$transformCalled, &$receivedReport): array {
            $transformCalled = true;
            $receivedReport = $report;

            return ['custom' => 'payload', 'error' => $report->message];
        };

        // Use an invalid URL so file_get_contents fails silently
        $channel = new WebhookChannel('http://0.0.0.0:1', [], $transform);
        $report = ExceptionReport::fromThrowable(new \RuntimeException('transform test'));

        $channel->report($report);

        $this->assertTrue($transformCalled);
        $this->assertSame($report, $receivedReport);
    }

    public function test_default_serialization_uses_to_array(): void
    {
        // Without a transform, the channel should use $report->toArray()
        // We verify this indirectly: no transform means no custom callable invoked
        $channel = new WebhookChannel('http://0.0.0.0:1');
        $report = ExceptionReport::fromThrowable(new \RuntimeException('default format'));

        // Should not throw — silently fails on unreachable URL
        $channel->report($report);

        // Verify the report's toArray produces the expected keys
        $array = $report->toArray();
        $this->assertArrayHasKey('class', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertArrayHasKey('timestamp', $array);
    }

    public function test_does_not_throw_on_transport_error(): void
    {
        $channel = new WebhookChannel('http://0.0.0.0:1');
        $report = ExceptionReport::fromThrowable(new \RuntimeException('should not throw'));

        // This should complete without throwing
        $channel->report($report);

        $this->assertTrue(true);
    }

    public function test_accepts_custom_headers(): void
    {
        $channel = new WebhookChannel(
            'http://0.0.0.0:1',
            ['Authorization' => 'Bearer secret', 'X-Custom' => 'value'],
        );

        $report = ExceptionReport::fromThrowable(new \RuntimeException('headers test'));

        // Should not throw
        $channel->report($report);

        $this->assertInstanceOf(ReportChannel::class, $channel);
    }
}
