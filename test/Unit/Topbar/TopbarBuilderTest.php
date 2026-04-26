<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Test\Unit\Topbar;

use Horde\Core\Service\PermissionService;
use Horde\Core\Service\PrefsService;
use Horde\Core\Session\HordeSession;
use Horde\Core\Topbar\TopbarBuilder;
use Horde\Core\Topbar\TopbarData;
use Horde\Url\Url;
use Horde_Exception;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TopbarBuilder::class)]
class TopbarBuilderTest extends TestCase
{
    private function createBuilder(
        ?Horde_Registry $registry = null,
        ?PrefsService $prefs = null,
        ?PermissionService $permissions = null,
        ?HordeSession $session = null,
    ): TopbarBuilder {
        $registry ??= $this->createMock(Horde_Registry::class);
        $prefs ??= $this->createMock(PrefsService::class);
        $permissions ??= $this->createMock(PermissionService::class);
        $session ??= $this->createMock(HordeSession::class);

        return new TopbarBuilder($registry, $prefs, $permissions, $session);
    }

    private function registryWithNoServiceLinks(): Horde_Registry
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('getServiceLink')->willThrowException(new Horde_Exception('No service'));
        return $registry;
    }

    public function testBuildReturnsTopbarData(): void
    {
        $registry = $this->registryWithNoServiceLinks();
        $registry->method('get')->willReturn('/horde');
        $registry->method('listApps')->willReturn([]);
        $registry->method('isAdmin')->willReturn(false);

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn(null);

        $builder = $this->createBuilder(registry: $registry, session: $session);
        $data = $builder->build();

        $this->assertInstanceOf(TopbarData::class, $data);
        $this->assertSame('/horde', $data->portalUrl);
    }

    public function testBuildWithApps(): void
    {
        $registry = $this->registryWithNoServiceLinks();
        $registry->method('get')->willReturn('/horde');
        $registry->method('listApps')->willReturn([
            'imp' => [
                'name' => 'Mail',
                'status' => 'active',
                'webroot' => '/imp',
            ],
        ]);
        $registry->method('isAdmin')->willReturn(false);
        $registry->method('getInitialPage')->willReturn('/imp/');

        $permissions = $this->createMock(PermissionService::class);
        $permissions->method('exists')->willReturn(false);

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')->willReturn(null);

        $builder = $this->createBuilder(
            registry: $registry,
            prefs: $prefs,
            permissions: $permissions,
            session: $session,
        );
        $data = $builder->build('horde');

        $this->assertNotEmpty($data->menuTree);
        $this->assertSame('imp', $data->menuTree[0]->id);
        $this->assertSame('Mail', $data->menuTree[0]->label);
    }

    public function testBuildSidebarWidth(): void
    {
        $registry = $this->registryWithNoServiceLinks();
        $registry->method('get')->willReturn('/horde');
        $registry->method('listApps')->willReturn([]);
        $registry->method('isAdmin')->willReturn(false);

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('testuser');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')
            ->willReturnCallback(function ($uid, $app, $key) {
                if ($key === 'sidebar_width') {
                    return '250';
                }
                return null;
            });

        $builder = $this->createBuilder(registry: $registry, prefs: $prefs, session: $session);
        $data = $builder->build();

        $this->assertSame(250, $data->sidebarWidth);
    }

    public function testBuildDefaultSidebarWidth(): void
    {
        $registry = $this->registryWithNoServiceLinks();
        $registry->method('get')->willReturn('/horde');
        $registry->method('listApps')->willReturn([]);
        $registry->method('isAdmin')->willReturn(false);

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn(null);

        $builder = $this->createBuilder(registry: $registry, session: $session);
        $data = $builder->build();

        $this->assertSame(150, $data->sidebarWidth);
    }

    public function testBuildLogoutUrlForAuthenticatedUser(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('get')->willReturn('/horde');
        $registry->method('listApps')->willReturn([]);
        $registry->method('isAdmin')->willReturn(false);
        $registry->method('getServiceLink')
            ->willReturnCallback(function (string $service) {
                if ($service === 'logout') {
                    return new Url('/horde/login/logout');
                }
                throw new Horde_Exception('No service');
            });

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn('admin');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')->willReturn(null);

        $builder = $this->createBuilder(registry: $registry, prefs: $prefs, session: $session);
        $data = $builder->build();

        $this->assertSame('/horde/login/logout', $data->logoutUrl);
        $this->assertNull($data->loginUrl);
    }

    public function testBuildLoginUrlForUnauthenticated(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('get')->willReturn('/horde');
        $registry->method('listApps')->willReturn([]);
        $registry->method('isAdmin')->willReturn(false);
        $registry->method('getServiceLink')
            ->willReturnCallback(function (string $service) {
                if ($service === 'login') {
                    return new Url('/horde/login');
                }
                throw new Horde_Exception('No service');
            });

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn(null);

        $builder = $this->createBuilder(registry: $registry, session: $session);
        $data = $builder->build();

        $this->assertNull($data->logoutUrl);
        $this->assertSame('/horde/login', $data->loginUrl);
    }

    public function testBuildFiltersHeadingsWithNoChildren(): void
    {
        $registry = $this->registryWithNoServiceLinks();
        $registry->method('get')->willReturn('/horde');
        $registry->method('listApps')->willReturn([
            'emptyheading' => [
                'name' => 'Empty',
                'status' => 'heading',
            ],
        ]);
        $registry->method('isAdmin')->willReturn(false);

        $session = $this->createMock(HordeSession::class);
        $session->method('getAuthId')->willReturn(null);

        $builder = $this->createBuilder(registry: $registry, session: $session);
        $data = $builder->build();

        $this->assertEmpty($data->menuTree);
    }
}
