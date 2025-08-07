<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter;

final class RateLimiter
{
    /** @var array<string, array<int, float>> */
    private array $fingerprintTimestamps = [];

    /** @var array<int, float> */
    private array $globalTimestamps = [];

    /**
     * @param  int  $maxReports  Maximum reports per fingerprint within the time window
     * @param  int  $windowSeconds  Time window in seconds
     */
    public function __construct(
        private readonly int $maxReports = 100,
        private readonly int $windowSeconds = 60,
    ) {}

    /**
     * Determine whether a report with the given fingerprint should be allowed.
     *
     * Returns true if both the per-fingerprint and global counts are under the limit.
     */
    public function shouldReport(string $fingerprint): bool
    {
        $now = microtime(true);
        $cutoff = $now - $this->windowSeconds;

        $this->pruneExpired($cutoff);

        $fingerprintCount = count($this->fingerprintTimestamps[$fingerprint] ?? []);
        if ($fingerprintCount >= $this->maxReports) {
            return false;
        }

        $globalCount = count($this->globalTimestamps);
        if ($globalCount >= $this->maxReports) {
            return false;
        }

        $this->fingerprintTimestamps[$fingerprint][] = $now;
        $this->globalTimestamps[] = $now;

        return true;
    }

    /**
     * Remove expired timestamps from all tracking arrays.
     */
    private function pruneExpired(float $cutoff): void
    {
        $this->globalTimestamps = array_values(
            array_filter($this->globalTimestamps, fn (float $ts): bool => $ts > $cutoff),
        );

        foreach ($this->fingerprintTimestamps as $fp => $timestamps) {
            $filtered = array_values(
                array_filter($timestamps, fn (float $ts): bool => $ts > $cutoff),
            );

            if ($filtered === []) {
                unset($this->fingerprintTimestamps[$fp]);
            } else {
                $this->fingerprintTimestamps[$fp] = $filtered;
            }
        }
    }
}
