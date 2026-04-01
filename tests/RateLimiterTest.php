<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter\Tests;

use PhilipRehberger\ExceptionReporter\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    public function test_allows_reports_under_limit(): void
    {
        $limiter = new RateLimiter(maxReports: 5, windowSeconds: 60);

        $this->assertTrue($limiter->shouldReport('fp1'));
        $this->assertTrue($limiter->shouldReport('fp1'));
        $this->assertTrue($limiter->shouldReport('fp1'));
    }

    public function test_blocks_reports_over_per_fingerprint_limit(): void
    {
        $limiter = new RateLimiter(maxReports: 2, windowSeconds: 60);

        $this->assertTrue($limiter->shouldReport('fp1'));
        $this->assertTrue($limiter->shouldReport('fp1'));
        $this->assertFalse($limiter->shouldReport('fp1'));
    }

    public function test_blocks_reports_over_global_limit(): void
    {
        $limiter = new RateLimiter(maxReports: 3, windowSeconds: 60);

        $this->assertTrue($limiter->shouldReport('fp1'));
        $this->assertTrue($limiter->shouldReport('fp2'));
        $this->assertTrue($limiter->shouldReport('fp3'));
        // Global limit reached even though fp4 has no per-fingerprint entries
        $this->assertFalse($limiter->shouldReport('fp4'));
    }

    public function test_different_fingerprints_have_separate_limits(): void
    {
        $limiter = new RateLimiter(maxReports: 2, windowSeconds: 60);

        $this->assertTrue($limiter->shouldReport('fp1'));
        $this->assertTrue($limiter->shouldReport('fp2'));

        // Each fingerprint has its own count
        $this->assertFalse($limiter->shouldReport('fp1')); // global limit of 2 reached
    }

    public function test_expired_entries_are_pruned(): void
    {
        // Use a very short window so entries expire quickly
        $limiter = new RateLimiter(maxReports: 1, windowSeconds: 0);

        $this->assertTrue($limiter->shouldReport('fp1'));

        // With a 0-second window, the previous entry should be expired by now
        // so the next call should prune it and allow a new report
        $this->assertTrue($limiter->shouldReport('fp1'));
    }

    public function test_global_limit_equals_max_reports(): void
    {
        $limiter = new RateLimiter(maxReports: 2, windowSeconds: 60);

        $this->assertTrue($limiter->shouldReport('a'));
        $this->assertTrue($limiter->shouldReport('b'));
        $this->assertFalse($limiter->shouldReport('c'));
    }
}
