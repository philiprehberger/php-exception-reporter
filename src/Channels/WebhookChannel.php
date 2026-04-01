<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter\Channels;

use PhilipRehberger\ExceptionReporter\Contracts\ReportChannel;
use PhilipRehberger\ExceptionReporter\ExceptionReport;

final class WebhookChannel implements ReportChannel
{
    /** @var callable(ExceptionReport): array<string, mixed>|null */
    private $transform;

    /**
     * @param  string  $url  The webhook endpoint URL
     * @param  array<string, string>  $headers  Additional HTTP headers
     * @param  (callable(ExceptionReport): array<string, mixed>)|null  $transform  Custom payload transformer
     */
    public function __construct(
        private readonly string $url,
        private readonly array $headers = [],
        ?callable $transform = null,
    ) {
        $this->transform = $transform;
    }

    /**
     * Send the exception report to the webhook endpoint via HTTP POST.
     */
    public function report(ExceptionReport $report): void
    {
        try {
            $payload = $this->transform !== null
                ? ($this->transform)($report)
                : $report->toArray();

            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            $headers = array_merge(
                ['Content-Type: application/json'],
                array_map(
                    fn (string $key, string $value): string => "{$key}: {$value}",
                    array_keys($this->headers),
                    array_values($this->headers),
                ),
            );

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $body,
                    'ignore_errors' => true,
                    'timeout' => 5,
                ],
            ]);

            @file_get_contents($this->url, false, $context);
        } catch (\Throwable) {
            // Silently fail — reporting should not crash the app
        }
    }
}
