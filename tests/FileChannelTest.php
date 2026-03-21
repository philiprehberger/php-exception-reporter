<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter\Tests;

use PhilipRehberger\ExceptionReporter\Channels\FileChannel;
use PhilipRehberger\ExceptionReporter\ExceptionReport;
use PHPUnit\Framework\TestCase;

final class FileChannelTest extends TestCase
{
    public function test_writes_json_line_to_file(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'file_channel_test_');
        $this->assertNotFalse($tmpFile);

        try {
            $channel = new FileChannel($tmpFile);
            $report = ExceptionReport::fromThrowable(new \RuntimeException('test write'));

            $channel->report($report);

            $contents = file_get_contents($tmpFile);
            $this->assertNotFalse($contents);

            $decoded = json_decode(trim($contents), true);
            $this->assertIsArray($decoded);
            $this->assertSame(\RuntimeException::class, $decoded['class']);
            $this->assertSame('test write', $decoded['message']);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_appends_multiple_reports_as_separate_lines(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'file_channel_test_');
        $this->assertNotFalse($tmpFile);

        try {
            $channel = new FileChannel($tmpFile);

            $channel->report(ExceptionReport::fromThrowable(new \RuntimeException('first')));
            $channel->report(ExceptionReport::fromThrowable(new \LogicException('second')));
            $channel->report(ExceptionReport::fromThrowable(new \InvalidArgumentException('third')));

            $contents = file_get_contents($tmpFile);
            $this->assertNotFalse($contents);

            $lines = array_filter(explode("\n", $contents), fn (string $line): bool => $line !== '');
            $this->assertCount(3, $lines);

            $first = json_decode($lines[0], true);
            $second = json_decode($lines[1], true);
            $third = json_decode($lines[2], true);

            $this->assertSame('first', $first['message']);
            $this->assertSame('second', $second['message']);
            $this->assertSame('third', $third['message']);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_creates_file_if_it_does_not_exist(): void
    {
        $tmpDir = sys_get_temp_dir().'/file_channel_test_'.uniqid();
        @mkdir($tmpDir, 0777, true);
        $filePath = $tmpDir.'/exceptions.log';

        try {
            $this->assertFileDoesNotExist($filePath);

            $channel = new FileChannel($filePath);
            $channel->report(ExceptionReport::fromThrowable(new \RuntimeException('create test')));

            $this->assertFileExists($filePath);

            $decoded = json_decode(trim(file_get_contents($filePath)), true);
            $this->assertSame('create test', $decoded['message']);
        } finally {
            @unlink($filePath);
            @rmdir($tmpDir);
        }
    }

    public function test_writes_valid_json_with_unescaped_slashes(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'file_channel_test_');
        $this->assertNotFalse($tmpFile);

        try {
            $channel = new FileChannel($tmpFile);
            $channel->report(ExceptionReport::fromThrowable(
                new \RuntimeException('path error'),
                ['path' => '/var/log/app.log'],
            ));

            $contents = trim(file_get_contents($tmpFile));

            // Slashes should not be escaped in the JSON output
            $this->assertStringContainsString('/var/log/app.log', $contents);

            $decoded = json_decode($contents, true);
            $this->assertSame('/var/log/app.log', $decoded['context']['path']);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_each_line_ends_with_newline(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'file_channel_test_');
        $this->assertNotFalse($tmpFile);

        try {
            $channel = new FileChannel($tmpFile);
            $channel->report(ExceptionReport::fromThrowable(new \RuntimeException('newline test')));

            $contents = file_get_contents($tmpFile);
            $this->assertNotFalse($contents);
            $this->assertStringEndsWith("\n", $contents);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_report_contains_all_expected_keys(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'file_channel_test_');
        $this->assertNotFalse($tmpFile);

        try {
            $channel = new FileChannel($tmpFile);
            $channel->report(ExceptionReport::fromThrowable(
                new \RuntimeException('keys test'),
                ['env' => 'testing'],
            ));

            $decoded = json_decode(trim(file_get_contents($tmpFile)), true);

            $this->assertArrayHasKey('class', $decoded);
            $this->assertArrayHasKey('message', $decoded);
            $this->assertArrayHasKey('file', $decoded);
            $this->assertArrayHasKey('line', $decoded);
            $this->assertArrayHasKey('trace', $decoded);
            $this->assertArrayHasKey('timestamp', $decoded);
            $this->assertArrayHasKey('context', $decoded);
        } finally {
            @unlink($tmpFile);
        }
    }
}
