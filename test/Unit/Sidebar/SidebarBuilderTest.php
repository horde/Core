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

namespace Horde\Core\Test\Unit\Sidebar;

use Horde\Core\Service\PrefsService;
use Horde\Core\Sidebar\SidebarBuilder;
use Horde\Core\Sidebar\SidebarData;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Exception;

#[CoversClass(SidebarBuilder::class)]
class SidebarBuilderTest extends TestCase
{
    public function testBuildReturnsSidebarData(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('callAppMethod')->willReturn(null);
        $registry->method('getAuth')->willReturn(null);

        $prefs = $this->createMock(PrefsService::class);

        $builder = new SidebarBuilder($registry, $prefs);
        $data = $builder->build();

        $this->assertInstanceOf(SidebarData::class, $data);
    }

    public function testBuildDefaultWidth(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('callAppMethod')->willReturn(null);
        $registry->method('getAuth')->willReturn(null);

        $prefs = $this->createMock(PrefsService::class);

        $builder = new SidebarBuilder($registry, $prefs);
        $data = $builder->build();

        $this->assertSame(150, $data->width);
    }

    public function testBuildCustomWidthFromPrefs(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('callAppMethod')->willReturn(null);
        $registry->method('getAuth')->willReturn('testuser');

        $prefs = $this->createMock(PrefsService::class);
        $prefs->method('getValue')
            ->with('testuser', 'horde', 'sidebar_width')
            ->willReturn('300');

        $builder = new SidebarBuilder($registry, $prefs);
        $data = $builder->build();

        $this->assertSame(300, $data->width);
    }

    public function testBuildCallsAppMethods(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->exactly(2))
            ->method('callAppMethod')
            ->willReturnCallback(function (string $app, string $method) {
                $this->assertSame('imp', $app);
                $this->assertContains($method, ['menu', 'sidebar']);
                return null;
            });
        $registry->method('getAuth')->willReturn(null);

        $prefs = $this->createMock(PrefsService::class);

        $builder = new SidebarBuilder($registry, $prefs);
        $builder->build('imp');
    }

    public function testBuildPassesCookieData(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('callAppMethod')->willReturn(null);
        $registry->method('getAuth')->willReturn(null);

        $prefs = $this->createMock(PrefsService::class);

        $builder = new SidebarBuilder($registry, $prefs);
        $data = $builder->build('horde', ['horde_sidebar_c_test' => '1']);

        $this->assertInstanceOf(SidebarData::class, $data);
    }

    public function testBuildHandlesExceptionGracefully(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('callAppMethod')
            ->willThrowException(new Exception('App not found'));
        $registry->method('getAuth')->willReturn(null);

        $prefs = $this->createMock(PrefsService::class);

        $builder = new SidebarBuilder($registry, $prefs);
        $data = $builder->build();

        $this->assertInstanceOf(SidebarData::class, $data);
        $this->assertEmpty($data->containers);
    }
}
