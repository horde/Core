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
use Horde\Core\Session\SessionAccess;
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

        $session = $this->createMock(SessionAccess::class);
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

        $session = $this->createMock(SessionAccess::class);
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

        $session = $this->createMock(SessionAccess::class);
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

        $session = $this->createMock(SessionAccess::class);
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

        $session = $this->createMock(SessionAccess::class);
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

        $session = $this->createMock(SessionAccess::class);
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

        $session = $this->createMock(SessionAccess::class);
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

    /**
     * Regression guard for the topbar i18n fix: application names must be
     * resolved against the owning app's gettext domain (a per-app fileroot
     * lookup + bindtextdomain + dgettext), while headings/links must keep the
     * ambient _() behaviour and must NOT be resolved via a per-app domain.
     *
     * This is deterministic (no locale/.mo dependency): it only checks that
     * the code looks up 'fileroot' for real apps and never for headings.
     */
    public function testHeadingNamesBypassOwningAppDomainResolution(): void
    {
        $filerootLookups = [];
        $registry = $this->registryWithNoServiceLinks();
        $registry->method('get')->willReturnCallback(
            function ($param, $app = null) use (&$filerootLookups) {
                if ($param === 'fileroot') {
                    $filerootLookups[] = $app;
                }
                return '/horde';
            }
        );
        $registry->method('listApps')->willReturn([
            'imp' => ['name' => 'Mail', 'status' => 'active', 'webroot' => '/imp'],
            'myheading' => ['name' => 'Group', 'status' => 'heading'],
            'child' => [
                'name' => 'Child',
                'status' => 'active',
                'webroot' => '/child',
                'menu_parent' => 'myheading',
            ],
        ]);
        $registry->method('isAdmin')->willReturn(false);
        $registry->method('getInitialPage')->willReturn('/x/');

        $permissions = $this->createMock(PermissionService::class);
        $permissions->method('exists')->willReturn(false);

        $session = $this->createMock(SessionAccess::class);
        $session->method('getAuthId')->willReturn(null);

        $builder = new TopbarBuilder(
            $registry,
            $this->createStub(PrefsService::class),
            $permissions,
            $session,
        );
        $builder->build('horde');

        $this->assertContains(
            'imp',
            $filerootLookups,
            'Real app names must be resolved against the app\'s own gettext domain'
        );
        $this->assertNotContains(
            'myheading',
            $filerootLookups,
            'Heading names must not be resolved via a per-app domain'
        );
    }

    /**
     * Behavioural regression guard: prove the app name is translated via the
     * owning app's domain even when that app is not the active default gettext
     * domain -- exactly the scenario that produced English names before the
     * fix. Uses a self-contained .mo fixture; skips where the environment
     * cannot perform gettext translation at all.
     */
    public function testResolvesAppNameViaOwningAppDomain(): void
    {
        $dir = sys_get_temp_dir() . '/topbar_gt_' . uniqid('', true);
        $lang = 'tstlang';
        /* Unique domain so PHP's per-process gettext cache can't collide with
         * another test that bound the same domain to a different directory. */
        $domain = 'fixtureapp' . substr(md5($dir), 0, 8);
        $moDir = $dir . '/locale/' . $lang . '/LC_MESSAGES';
        mkdir($moDir, 0777, true);
        self::writeMo($moDir . '/' . $domain . '.mo', [
            '' => "Content-Type: text/plain; charset=UTF-8\n",
            'Mail' => 'FIXTURE-MAIL',
        ]);

        $oldLang = getenv('LANGUAGE');
        putenv('LANGUAGE=' . $lang);
        setlocale(LC_MESSAGES, 'en_US.UTF-8', 'C.UTF-8', 'en_US.utf8', 'C');

        $restore = static function () use ($oldLang): void {
            if ($oldLang === false) {
                putenv('LANGUAGE');
            } else {
                putenv('LANGUAGE=' . $oldLang);
            }
        };

        /* Confirm gettext can translate in this environment before asserting;
         * otherwise the test would be a false negative rather than a guard. */
        bindtextdomain($domain, $dir . '/locale');
        if (function_exists('bind_textdomain_codeset')) {
            bind_textdomain_codeset($domain, 'UTF-8');
        }
        if (dgettext($domain, 'Mail') !== 'FIXTURE-MAIL') {
            $restore();
            $this->markTestSkipped('gettext translation not available in this environment');
        }

        $registry = $this->registryWithNoServiceLinks();
        $registry->method('get')->willReturnCallback(
            static function ($param, $app = null) use ($domain, $dir) {
                return ($param === 'fileroot' && $app === $domain) ? $dir : '/horde';
            }
        );
        $registry->method('listApps')->willReturn([
            $domain => ['name' => 'Mail', 'status' => 'active', 'webroot' => '/x'],
        ]);
        $registry->method('isAdmin')->willReturn(false);
        $registry->method('getInitialPage')->willReturn('/x/');

        $permissions = $this->createMock(PermissionService::class);
        $permissions->method('exists')->willReturn(false);

        $session = $this->createMock(SessionAccess::class);
        $session->method('getAuthId')->willReturn(null);

        $builder = new TopbarBuilder(
            $registry,
            $this->createStub(PrefsService::class),
            $permissions,
            $session,
        );
        $data = $builder->build('horde');
        $restore();

        $node = null;
        foreach ($data->menuTree as $candidate) {
            if ($candidate->id === $domain) {
                $node = $candidate;
                break;
            }
        }

        $this->assertNotNull($node, 'The app node should be present in the menu tree');
        $this->assertSame(
            'FIXTURE-MAIL',
            $node->label,
            'App name must be translated via the owning app\'s gettext domain, '
                . 'not the active default domain'
        );
    }

    /**
     * Write a minimal little-endian GNU gettext .mo catalog.
     *
     * @param string               $path     Target .mo path.
     * @param array<string,string> $entries  msgid => msgstr (include the ''
     *                                        header entry).
     */
    private static function writeMo(string $path, array $entries): void
    {
        ksort($entries, SORT_STRING);
        $ids = array_keys($entries);
        $strs = array_values($entries);
        $count = count($entries);

        $idBlock = '';
        $idTable = [];
        foreach ($ids as $id) {
            $idTable[] = [strlen($id), strlen($idBlock)];
            $idBlock .= $id . "\0";
        }

        $strBlock = '';
        $strTable = [];
        foreach ($strs as $str) {
            $strTable[] = [strlen($str), strlen($strBlock)];
            $strBlock .= $str . "\0";
        }

        $headerSize = 28;
        $oTableOffset = $headerSize;
        $tTableOffset = $oTableOffset + ($count * 8);
        $idBlockOffset = $tTableOffset + ($count * 8);
        $strBlockOffset = $idBlockOffset + strlen($idBlock);

        $out = pack('V', 0x950412de) . pack('V', 0) . pack('V', $count)
            . pack('V', $oTableOffset) . pack('V', $tTableOffset)
            . pack('V', 0) . pack('V', 0);

        foreach ($idTable as [$len, $off]) {
            $out .= pack('V', $len) . pack('V', $idBlockOffset + $off);
        }
        foreach ($strTable as [$len, $off]) {
            $out .= pack('V', $len) . pack('V', $strBlockOffset + $off);
        }

        file_put_contents($path, $out . $idBlock . $strBlock);
    }
}
