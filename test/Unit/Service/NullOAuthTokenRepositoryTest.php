<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\Service;

use Horde\Core\Service\Exception\OAuthTokenNotFoundException;
use Horde\Core\Service\NullOAuthTokenRepository;
use Horde\Core\Service\OAuthTokenRepository;
use Horde\Oauth\Client\TokenSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullOAuthTokenRepository::class)]
final class NullOAuthTokenRepositoryTest extends TestCase
{
    private NullOAuthTokenRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new NullOAuthTokenRepository();
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(OAuthTokenRepository::class, $this->repository);
    }

    public function testExistsReturnsFalse(): void
    {
        self::assertFalse($this->repository->exists('user1', 'google'));
    }

    public function testSaveIsSilentlyAccepted(): void
    {
        $tokens = new TokenSet('access-token', 'Bearer');
        $this->repository->save('user1', 'google', $tokens);
        self::assertFalse($this->repository->exists('user1', 'google'));
    }

    public function testDeleteIsSilentlyAccepted(): void
    {
        $this->repository->delete('user1', 'google');
        self::assertFalse($this->repository->exists('user1', 'google'));
    }

    public function testLoadThrows(): void
    {
        $this->expectException(OAuthTokenNotFoundException::class);
        $this->repository->load('user1', 'google');
    }
}
