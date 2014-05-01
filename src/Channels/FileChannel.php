<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter\Channels;

use PhilipRehberger\ExceptionReporter\Contracts\ReportChannel;
use PhilipRehberger\ExceptionReporter\ExceptionReport;

final class FileChannel implements ReportChannel
{
    public function __construct(
        private readonly string $filePath,
    ) {}

    public function report(ExceptionReport $report): void
    {
        $line = json_encode($report->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($this->filePath, $line."\n", FILE_APPEND | LOCK_EX);
    }
}
