<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter;

use DateTimeImmutable;

final class ReportSummary
{
    /** @var array<ExceptionReport> */
    private readonly array $reports;

    /**
     * @param  array<ExceptionReport>  $reports
     */
    public function __construct(array $reports)
    {
        $this->reports = array_values($reports);
    }

    /**
     * Total number of reports.
     */
    public function totalCount(): int
    {
        return count($this->reports);
    }

    /**
     * Count of unique fingerprints across all reports.
     */
    public function uniqueCount(): int
    {
        $fingerprints = [];

        foreach ($this->reports as $report) {
            $fingerprints[$report->fingerprint()] = true;
        }

        return count($fingerprints);
    }

    /**
     * Top exceptions sorted by frequency (most frequent first).
     *
     * @param  int  $limit  Maximum number of results
     * @return array<int, array{message: string, count: int, lastSeen: DateTimeImmutable}>
     */
    public function topExceptions(int $limit = 5): array
    {
        /** @var array<string, array{message: string, count: int, lastSeen: DateTimeImmutable}> */
        $grouped = [];

        foreach ($this->reports as $report) {
            $fingerprint = $report->fingerprint();

            if (! isset($grouped[$fingerprint])) {
                $grouped[$fingerprint] = [
                    'message' => $report->message,
                    'count' => 0,
                    'lastSeen' => $report->timestamp,
                ];
            }

            $grouped[$fingerprint]['count']++;

            if ($report->timestamp > $grouped[$fingerprint]['lastSeen']) {
                $grouped[$fingerprint]['lastSeen'] = $report->timestamp;
            }
        }

        usort($grouped, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return array_slice($grouped, 0, $limit);
    }

    /**
     * Earliest report timestamp, or null if no reports exist.
     */
    public function since(): ?DateTimeImmutable
    {
        if ($this->reports === []) {
            return null;
        }

        $earliest = $this->reports[0]->timestamp;

        foreach ($this->reports as $report) {
            if ($report->timestamp < $earliest) {
                $earliest = $report->timestamp;
            }
        }

        return $earliest;
    }

    /**
     * Latest report timestamp, or null if no reports exist.
     */
    public function until(): ?DateTimeImmutable
    {
        if ($this->reports === []) {
            return null;
        }

        $latest = $this->reports[0]->timestamp;

        foreach ($this->reports as $report) {
            if ($report->timestamp > $latest) {
                $latest = $report->timestamp;
            }
        }

        return $latest;
    }
}
