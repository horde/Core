<?php

declare(strict_types=1);

namespace Horde\Core\Service;

final class UnsupportedPurposeException extends \RuntimeException
{
    public function __construct(
        private readonly string $providerId,
        private readonly ServicePurpose $purpose,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        if ($message === '') {
            $message = sprintf(
                "Provider '%s' does not support purpose '%s'",
                $providerId,
                $purpose->identifier()
            );
        }
        parent::__construct($message, $code, $previous);
    }

    public function providerId(): string
    {
        return $this->providerId;
    }

    public function purpose(): ServicePurpose
    {
        return $this->purpose;
    }
}
