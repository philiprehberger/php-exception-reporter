# PHP Exception Reporter

[![Tests](https://github.com/philiprehberger/php-exception-reporter/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/php-exception-reporter/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/php-exception-reporter.svg)](https://packagist.org/packages/philiprehberger/php-exception-reporter)
[![License](https://img.shields.io/github/license/philiprehberger/php-exception-reporter)](LICENSE)

Lightweight exception reporting to log channels and webhooks.

## Requirements

- PHP ^8.2

## Installation

```bash
composer require philiprehberger/php-exception-reporter
```

## Usage

### Basic reporting with a callback

```php
use PhilipRehberger\ExceptionReporter\ExceptionReporter;
use PhilipRehberger\ExceptionReporter\Channels\CallbackChannel;

$reporter = new ExceptionReporter();

$reporter->addChannel(new CallbackChannel(function ($report) {
    error_log("[{$report->class}] {$report->message} in {$report->file}:{$report->line}");
}));

try {
    riskyOperation();
} catch (\Throwable $e) {
    $reporter->capture($e);
}
```

### File channel

```php
use PhilipRehberger\ExceptionReporter\Channels\FileChannel;

$reporter->addChannel(new FileChannel('/var/log/app-exceptions.log'));

// Each report is written as a JSON line
$reporter->capture(new \RuntimeException('Something failed'));
```

### Multiple channels

```php
$reporter
    ->addChannel(new CallbackChannel(function ($report) {
        // Send to your monitoring service
    }))
    ->addChannel(new FileChannel('/var/log/exceptions.log'));
```

### Deduplication

Prevent the same exception (same class, file, and line) from being reported more than once:

```php
$reporter->enableDeduplication();

$exception = new \RuntimeException('flaky');
$reporter->capture($exception); // Reported
$reporter->capture($exception); // Skipped (duplicate)

$reporter->resetFingerprints(); // Clear dedup state
$reporter->capture($exception); // Reported again
```

### Adding context

```php
$reporter->capture($exception, [
    'user_id' => 42,
    'request_url' => '/checkout',
]);
```

### Custom channels

Implement the `ReportChannel` interface to build your own channel:

```php
use PhilipRehberger\ExceptionReporter\Contracts\ReportChannel;
use PhilipRehberger\ExceptionReporter\ExceptionReport;

class SlackChannel implements ReportChannel
{
    public function report(ExceptionReport $report): void
    {
        // POST to Slack webhook with $report->toArray()
    }
}
```

## API

### `ExceptionReporter`

| Method | Description |
|---|---|
| `addChannel(ReportChannel $channel): self` | Register a reporting channel |
| `enableDeduplication(): self` | Enable fingerprint-based deduplication |
| `capture(Throwable $e, array $context = []): ExceptionReport` | Capture and report an exception |
| `resetFingerprints(): void` | Clear deduplication state |

### `ExceptionReport`

| Property / Method | Description |
|---|---|
| `string $class` | Exception class name |
| `string $message` | Exception message |
| `string $file` | File where the exception was thrown |
| `int $line` | Line number |
| `string $trace` | Stack trace as string |
| `DateTimeImmutable $timestamp` | When the exception was captured |
| `array $context` | Additional context data |
| `?string $previousClass` | Previous exception class, if any |
| `?string $previousMessage` | Previous exception message, if any |
| `fingerprint(): string` | MD5 hash of class + file + line |
| `toArray(): array` | Serialize to array |
| `fromThrowable(Throwable, array): self` | Create from a throwable |

### Channels

| Channel | Description |
|---|---|
| `CallbackChannel` | Invokes a user-provided callable |
| `FileChannel` | Appends JSON-encoded reports to a file |

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## License

MIT
