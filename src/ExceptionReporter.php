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

    private ?RateLimiter $rateLimiter = null;

    /** @var array<ExceptionReport> */
    private array $history = [];

    private int $maxHistory = 1000;

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
     * Enable rate limiting to prevent flooding during cascading failures.
     *
     * @param  int  $maxReports  Maximum reports per fingerprint (and globally) within the window
     * @param  int  $windowSeconds  Time window in seconds
     */
    public function rateLimit(int $maxReports, int $windowSeconds = 60): self
    {
        $this->rateLimiter = new RateLimiter($maxReports, $windowSeconds);

        return $this;
    }

    /**
     * Return a summary of stored exception reports.
     */
    public function summary(): ReportSummary
    {
        return new ReportSummary($this->history);
    }

    /**
     * Clear the stored report history.
     */
    public function clearHistory(): void
    {
        $this->history = [];
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

        $fingerprint = $report->fingerprint();

        if ($this->deduplication) {
            if (isset($this->fingerprints[$fingerprint])) {
                return $report;
            }
            $this->fingerprints[$fingerprint] = true;
        }

        if ($this->rateLimiter !== null && ! $this->rateLimiter->shouldReport($fingerprint)) {
            return $report;
        }

        foreach ($this->channels as $channel) {
            try {
                $channel->report($report);
            } catch (\Throwable) {
                // Silently ignore channel failures to prevent error loops
            }
        }

        $this->reportCount++;

        if (count($this->history) < $this->maxHistory) {
            $this->history[] = $report;
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
