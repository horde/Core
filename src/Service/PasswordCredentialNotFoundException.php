<?php
/**
 * Exception thrown when password credential is not found.
 *
 * @package Horde_Core
 */

declare(strict_types=1);

namespace Horde\Core\Service;

class PasswordCredentialNotFoundException extends \RuntimeException
{
    public function __construct(
        private readonly string $userId,
        private readonly string $providerId,
        private readonly ServicePurpose $purpose,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        if ($message === '') {
            $message = sprintf(
                'Password credential not found for user "%s", provider "%s", purpose "%s"',
                $userId,
                $providerId,
                $purpose->identifier()
            );
        }
        parent::__construct($message, $code, $previous);
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function getPurpose(): ServicePurpose
    {
        return $this->purpose;
    }
}
