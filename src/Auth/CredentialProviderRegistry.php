<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @license http://www.horde.org/licenses/lgpl21 LGPL-2.1
 */

namespace Horde\Core\Auth;

use DateTimeImmutable;
use Horde\Auth\AuthResultFail;
use Horde\Auth\AuthResultSuccess;
use Horde\Auth\CredentialProvider;

/**
 * Multi-backend credential provider that tries providers in order.
 *
 * Returns the first successful result. If all providers fail, returns
 * an AuthResultFail with sub-results from each backend in metadata.
 * Implements CredentialProvider itself for transparent composition.
 */
class CredentialProviderRegistry implements CredentialProvider
{
    /** @var list<CredentialProvider> */
    private readonly array $providers;

    public function __construct(CredentialProvider ...$providers)
    {
        $this->providers = array_values($providers);
    }

    public function validate(string $userId, array $credentials): AuthResultSuccess|AuthResultFail
    {
        $subResults = [];

        foreach ($this->providers as $provider) {
            $result = $provider->validate($userId, $credentials);

            if ($result instanceof AuthResultSuccess) {
                return $result;
            }

            $subResults[] = $result;
        }

        return new AuthResultFail(
            'composite',
            new DateTimeImmutable(),
            [
                'reason' => 'all_providers_failed',
                'sub_results' => $subResults,
            ]
        );
    }
}
