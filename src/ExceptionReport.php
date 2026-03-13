<?php

declare(strict_types=1);

namespace PhilipRehberger\ExceptionReporter;

use DateTimeImmutable;

final class ExceptionReport
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $class,
        public readonly string $message,
        public readonly string $file,
        public readonly int $line,
        public readonly string $trace,
        public readonly DateTimeImmutable $timestamp,
        public readonly array $context = [],
        public readonly ?string $previousClass = null,
        public readonly ?string $previousMessage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public static function fromThrowable(\Throwable $exception, array $context = []): self
    {
        $previous = $exception->getPrevious();

        return new self(
            class: $exception::class,
            message: $exception->getMessage(),
            file: $exception->getFile(),
            line: $exception->getLine(),
            trace: $exception->getTraceAsString(),
            timestamp: new DateTimeImmutable,
            context: $context,
            previousClass: $previous !== null ? $previous::class : null,
            previousMessage: $previous?->getMessage(),
        );
    }

    /**
     * Generate a fingerprint for deduplication (class + file + line).
     */
    public function fingerprint(): string
    {
        return md5($this->class.':'.$this->file.':'.$this->line);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'class' => $this->class,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'trace' => $this->trace,
            'timestamp' => $this->timestamp->format('c'),
            'context' => $this->context,
        ];
    }
}
