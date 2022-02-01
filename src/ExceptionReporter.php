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

    /** @var array<string, mixed> */
    private array $persistentContext = [];

    /** @var (callable(\Throwable): bool)|null */
    private $filter = null;

    private int $reportCount = 0;

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
     * Return a new reporter instance with persistent context fields merged in.
     *
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): self
    {
        $clone = clone $this;
        $clone->persistentContext = array_merge($this->persistentContext, $context);

        return $clone;
    }

    /**
     * Set a filter callable — if it returns false, the exception is not reported.
     *
     * @param  callable(\Throwable): bool  $filter
     */
    public function setFilter(callable $filter): self
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * Return the number of exceptions reported by this instance.
     */
    public function count(): int
    {
        return $this->reportCount;
    }

    /**
     * Capture and report an exception to all channels.
     *
     * @param  array<string, mixed>  $context  Additional context to include
     */
    public function capture(\Throwable $exception, array $context = []): ExceptionReport
    {
        $mergedContext = array_merge($this->persistentContext, $context);
        $report = ExceptionReport::fromThrowable($exception, $mergedContext);

        if ($this->filter !== null && ($this->filter)($exception) === false) {
            return $report;
        }

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

        $this->reportCount++;

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
