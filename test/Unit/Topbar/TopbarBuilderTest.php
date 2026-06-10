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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * TopbarBuilder always reads from registry+session+prefs+permissions during
 * build(). Tests pin those reads as `atLeastOnce` and check the resulting
 * TopbarData. Tests that pin a particular call shape (logoutUrl, sidebar
 * width, etc.) inject a more focused mock.
 */
#[CoversClass(TopbarBuilder::class)]
class TopbarBuilderTest extends TestCase
{
    /**
     * Registry mock that throws on every getServiceLink lookup. Most tests
     * don't care about service links and want them suppressed.
     */
    private function registryWithNoServiceLinks(): MockObject
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('getServiceLink')
            ->willThrowException(new Horde_Exception('No service'));

        return $registry;
    }

    /**
     * Pin the registry calls every build() makes regardless of the path
     * under test: get('webroot'/'version'), listApps, isAdmin.
     */
    private function pinCommonRegistryReads(MockObject $registry): void
    {
        $registry->expects($this->atLeastOnce())->method('get');
        $registry->expects($this->atLeastOnce())->method('listApps');
        $registry->expects($this->atLeastOnce())->method('isAdmin');
    }

    public function testBuildReturnsTopbarData(): void
    {
        $registry = $this->registryWithNoServiceLinks();
        $registry->method('get')->willReturn('/horde');
        $registry->method('listApps')->willReturn([]);
        $registry->method('isAdmin')->willReturn(false);
        $this->pinCommonRegistryReads($registry);

        $session = $this->createMock(HordeSession::class);
        $session->expects($this->atLeastOnce())
            ->method('getAuthId')->willReturn(null);

        $builder = new TopbarBuilder(
            $registry,
            $this->createStub(PrefsService::class),
            $this->createStub(PermissionService::class),
            $session,
        );
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
        $this->pinCommonRegistryReads($registry);

        $permissions = $this->createMock(PermissionService::class);
        $permissions->expects($this->atLeastOnce())
            ->method('exists')->willReturn(false);

        $session = $this->createMock(HordeSession::class);
        $session->expects($this->atLeastOnce())
            ->method('getAuthId')->willReturn('testuser');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->atLeastOnce())
            ->method('getValue')->willReturn(null);

        $builder = new TopbarBuilder($registry, $prefs, $permissions, $session);
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
        $this->pinCommonRegistryReads($registry);

        $session = $this->createMock(HordeSession::class);
        $session->expects($this->atLeastOnce())
            ->method('getAuthId')->willReturn('testuser');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->expects($this->atLeastOnce())
            ->method('getValue')
            ->willReturnCallback(function ($uid, $app, $key) {
                if ($key === 'sidebar_width') {
                    return '250';
                }
                return null;
            });

        $builder = new TopbarBuilder(
            $registry,
            $prefs,
            $this->createStub(PermissionService::class),
            $session,
        );
        $data = $builder->build();

        $this->assertSame(250, $data->sidebarWidth);
    }

    public function testBuildDefaultSidebarWidth(): void
    {
        $registry = $this->registryWithNoServiceLinks();
        $registry->method('get')->willReturn('/horde');
        $registry->method('listApps')->willReturn([]);
        $registry->method('isAdmin')->willReturn(false);
        $this->pinCommonRegistryReads($registry);

        $session = $this->createMock(HordeSession::class);
        $session->expects($this->atLeastOnce())
            ->method('getAuthId')->willReturn(null);

        $builder = new TopbarBuilder(
            $registry,
            $this->createStub(PrefsService::class),
            $this->createStub(PermissionService::class),
            $session,
        );
        $data = $builder->build();

        $this->assertSame(150, $data->sidebarWidth);
    }

    public function testBuildLogoutUrlForAuthenticatedUser(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('get')->willReturn('/horde');
        $registry->method('listApps')->willReturn([]);
        $registry->method('isAdmin')->willReturn(false);
        $this->pinCommonRegistryReads($registry);
        $registry->expects($this->atLeastOnce())
            ->method('getServiceLink')
            ->willReturnCallback(function (string $service) {
                if ($service === 'logout') {
                    return new Url('/horde/login/logout');
                }
                throw new Horde_Exception('No service');
            });

        $session = $this->createMock(HordeSession::class);
        $session->expects($this->atLeastOnce())
            ->method('getAuthId')->willReturn('admin');

        $builder = new TopbarBuilder(
            $registry,
            $this->createStub(PrefsService::class),
            $this->createStub(PermissionService::class),
            $session,
        );
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
        $this->pinCommonRegistryReads($registry);
        $registry->expects($this->atLeastOnce())
            ->method('getServiceLink')
            ->willReturnCallback(function (string $service) {
                if ($service === 'login') {
                    return new Url('/horde/login');
                }
                throw new Horde_Exception('No service');
            });

        $session = $this->createMock(HordeSession::class);
        $session->expects($this->atLeastOnce())
            ->method('getAuthId')->willReturn(null);

        $builder = new TopbarBuilder(
            $registry,
            $this->createStub(PrefsService::class),
            $this->createStub(PermissionService::class),
            $session,
        );
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
        $this->pinCommonRegistryReads($registry);

        $session = $this->createMock(HordeSession::class);
        $session->expects($this->atLeastOnce())
            ->method('getAuthId')->willReturn(null);

        $builder = new TopbarBuilder(
            $registry,
            $this->createStub(PrefsService::class),
            $this->createStub(PermissionService::class),
            $session,
        );
        $data = $builder->build();

        // The heading with no children should be filtered out.
        // The menuTree may still contain the always-present settings node.
        foreach ($data->menuTree as $node) {
            $this->assertNotSame('emptyheading', $node->id, 'Empty heading should be filtered out');
        }
    }
}
