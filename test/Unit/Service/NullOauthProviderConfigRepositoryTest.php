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

use Horde\Core\Service\Exception\OauthProviderConfigNotFoundException;
use Horde\Core\Service\NullOauthProviderConfigRepository;
use Horde\Core\Service\OauthProviderConfigRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullOauthProviderConfigRepository::class)]
final class NullOauthProviderConfigRepositoryTest extends TestCase
{
    private NullOauthProviderConfigRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new NullOauthProviderConfigRepository();
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(OauthProviderConfigRepository::class, $this->repository);
    }

    public function testGetThrows(): void
    {
        $this->expectException(OauthProviderConfigNotFoundException::class);
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
