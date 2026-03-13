<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter;

use PhilipRehberger\ExceptionReporter\Contracts\ReportChannel;

final class ExceptionReporter
{
    /** @var array<ReportChannel> */
    private array $channels = [];

    /** @var array<string, true> */
    private array $fingerprints = [];

    private bool $deduplication = false;

    /**
     * Add a reporting channel.
     */
    public function addChannel(ReportChannel $channel): self
    {
        $this->channels[] = $channel;

        return $this;
    }

    /**
     * Enable deduplication — same exception (class+file+line) is only reported once.
     */
    public function enableDeduplication(): self
    {
        $this->deduplication = true;

        return $this;
    }

    /**
     * Capture and report an exception to all channels.
     *
     * @param  array<string, mixed>  $context  Additional context to include
     */
    public function capture(\Throwable $exception, array $context = []): ExceptionReport
    {
        $report = ExceptionReport::fromThrowable($exception, $context);

        if ($this->deduplication) {
            $fingerprint = $report->fingerprint();
            if (isset($this->fingerprints[$fingerprint])) {
                return $report;
            }
            $this->fingerprints[$fingerprint] = true;
        }

        foreach ($this->channels as $channel) {
            try {
                $channel->report($report);
            } catch (\Throwable) {
                // Silently ignore channel failures to prevent error loops
            }
        }

        return $report;
    }

    /**
     * Reset deduplication fingerprints.
     */
    public function resetFingerprints(): void
    {
        $this->fingerprints = [];
    }
}
