<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter\Contracts;

use PhilipRehberger\ExceptionReporter\ExceptionReport;

interface ReportChannel
{
    public function report(ExceptionReport $report): void;
}
