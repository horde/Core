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

use Horde\Core\Service\Exception\OAuthProviderConfigNotFoundException;
use Horde\Core\Service\NullOAuthProviderConfigRepository;
use Horde\Core\Service\OAuthProviderConfigRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullOAuthProviderConfigRepository::class)]
final class NullOAuthProviderConfigRepositoryTest extends TestCase
{
    private NullOAuthProviderConfigRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new NullOAuthProviderConfigRepository();
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(OAuthProviderConfigRepository::class, $this->repository);
    }

    public function testGetThrows(): void
    {
        $this->expectException(OAuthProviderConfigNotFoundException::class);
        $this->repository->get('google');
    }

    public function testListAllReturnsEmptyArray(): void
    {
        self::assertSame([], $this->repository->listAll());
    }

    public function testListEnabledReturnsEmptyArray(): void
    {
        self::assertSame([], $this->repository->listEnabled());
    }

    public function testExistsReturnsFalse(): void
    {
        self::assertFalse($this->repository->exists('google'));
    }

    public function testSaveIsSilentlyAccepted(): void
    {
        $this->repository->save('google', ['type' => 'oidc', 'name' => 'Google']);
        self::assertFalse($this->repository->exists('google'));
    }

    public function testDeleteIsSilentlyAccepted(): void
    {
        $this->repository->delete('google');
        self::assertFalse($this->repository->exists('google'));
    }
}
