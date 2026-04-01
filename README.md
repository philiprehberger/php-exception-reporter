# PHP Exception Reporter

[![Tests](https://github.com/philiprehberger/php-exception-reporter/actions/workflows/tests.yml/badge.svg)](https://github.com/philiprehberger/php-exception-reporter/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/philiprehberger/php-exception-reporter.svg)](https://packagist.org/packages/philiprehberger/php-exception-reporter)
[![Last updated](https://img.shields.io/github/last-commit/philiprehberger/php-exception-reporter)](https://github.com/philiprehberger/php-exception-reporter/commits/main)

Lightweight exception reporting to log channels and webhooks.

## Requirements

- PHP 8.2+

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

### Persistent context

Attach context fields that are included in every subsequent report. `withContext()` returns a new immutable instance:

```php
$reporter = $reporter->withContext([
    'request_id' => 'abc-123',
    'user_id' => 42,
]);

// Both reports will include request_id and user_id
$reporter->capture(new \RuntimeException('first'));
$reporter->capture(new \LogicException('second'));

// Per-call context is merged with persistent context
$reporter->capture($exception, ['action' => 'checkout']);
```

### Filtering exceptions

Skip certain exceptions from being reported using a filter callable:

```php
$reporter->setFilter(function (\Throwable $e): bool {
    // Return false to skip reporting
    return !$e instanceof \DeprecationException;
});

$reporter->capture(new \DeprecationException('old API')); // Skipped
$reporter->capture(new \RuntimeException('real error'));   // Reported
```

### Webhook channel

Send exception reports to an HTTP endpoint:

```php
use PhilipRehberger\ExceptionReporter\Channels\WebhookChannel;

$reporter->addChannel(new WebhookChannel('https://example.com/webhook'));

// With custom headers
$reporter->addChannel(new WebhookChannel(
    'https://example.com/webhook',
    ['Authorization' => 'Bearer secret'],
));

// With a custom payload transformer
$reporter->addChannel(new WebhookChannel(
    'https://example.com/webhook',
    [],
    fn ($report) => ['text' => "[{$report->class}] {$report->message}"],
));
```

### Rate limiting

Prevent flooding during cascading failures by limiting how many reports are dispatched:

```php
$reporter->rateLimit(maxReports: 10, windowSeconds: 60);

// Only the first 10 reports per fingerprint (and 10 globally) within 60 seconds are sent
for ($i = 0; $i < 100; $i++) {
    $reporter->capture(new \RuntimeException('storm'));
}
```

### Summary reports

Access an aggregated summary of captured exceptions:

```php
$reporter->capture(new \RuntimeException('db timeout'));
$reporter->capture(new \RuntimeException('db timeout'));
$reporter->capture(new \LogicException('bad state'));

$summary = $reporter->summary();

$summary->totalCount();    // 3
$summary->uniqueCount();   // 2
$summary->since();         // DateTimeImmutable of earliest report
$summary->until();         // DateTimeImmutable of latest report
$summary->topExceptions(); // [{message, count, lastSeen}] sorted by frequency

$reporter->clearHistory(); // Reset stored reports
```

### Tracking report count

```php
$reporter->capture(new \RuntimeException('one'));
$reporter->capture(new \LogicException('two'));

echo $reporter->count(); // 2
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
| `withContext(array $context): self` | Return a new instance with persistent context fields |
| `setFilter(callable $filter): self` | Set a filter; return `false` to skip reporting |
| `count(): int` | Number of exceptions reported by this instance |
| `rateLimit(int $maxReports, int $windowSeconds = 60): self` | Enable rate limiting to cap reports per window |
| `summary(): ReportSummary` | Return an aggregated summary of stored reports |
| `clearHistory(): void` | Clear the stored report history |

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
| `WebhookChannel` | Sends JSON-encoded reports to an HTTP endpoint via POST |

### `RateLimiter`

| Method | Description |
|---|---|
| `__construct(int $maxReports = 100, int $windowSeconds = 60)` | Create a rate limiter with per-fingerprint and global limits |
| `shouldReport(string $fingerprint): bool` | Check if a report should be allowed within the current window |

### `ReportSummary`

| Method | Description |
|---|---|
| `totalCount(): int` | Total number of stored reports |
| `uniqueCount(): int` | Count of unique exception fingerprints |
| `topExceptions(int $limit = 5): array` | Top exceptions sorted by frequency with message, count, and lastSeen |
| `since(): ?DateTimeImmutable` | Earliest report timestamp |
| `until(): ?DateTimeImmutable` | Latest report timestamp |

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

## Support

If you find this project useful:

⭐ [Star the repo](https://github.com/philiprehberger/php-exception-reporter)

🐛 [Report issues](https://github.com/philiprehberger/php-exception-reporter/issues?q=is%3Aissue+is%3Aopen+label%3Abug)

💡 [Suggest features](https://github.com/philiprehberger/php-exception-reporter/issues?q=is%3Aissue+is%3Aopen+label%3Aenhancement)

❤️ [Sponsor development](https://github.com/sponsors/philiprehberger)

🌐 [All Open Source Projects](https://philiprehberger.com/open-source-packages)

💻 [GitHub Profile](https://github.com/philiprehberger)

🔗 [LinkedIn Profile](https://www.linkedin.com/in/philiprehberger)

## License

[MIT](LICENSE)
