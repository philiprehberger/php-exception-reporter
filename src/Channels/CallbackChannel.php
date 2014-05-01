<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter\Channels;

use PhilipRehberger\ExceptionReporter\Contracts\ReportChannel;
use PhilipRehberger\ExceptionReporter\ExceptionReport;

final class CallbackChannel implements ReportChannel
{
    /** @var callable(ExceptionReport): void */
    private $callback;

    /**
     * @param  callable(ExceptionReport): void  $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function report(ExceptionReport $report): void
    {
        ($this->callback)($report);
    }
}
