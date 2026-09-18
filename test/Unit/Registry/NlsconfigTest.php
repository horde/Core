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

namespace Horde\Core\Test\Unit\Registry;

use Horde\Core\Config\BackendConfigLoader;
use Horde\Core\LanguageContext;
use Horde\Core\Registry\Nlsconfig;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionAccessor;
use Horde\SessionHandler\SessionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the PSR-4, DI-friendly Horde\Core\Registry\Nlsconfig
 * LanguageContext implementation.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
#[CoversClass(Nlsconfig::class)]
class NlsconfigTest extends TestCase
{
    private string $tempDir;
    private SessionAccessor $sessionAccessor;
    private BackendConfigLoader $configLoader;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/horde-nlsconfig-test-' . uniqid();
        $vendorDir = $this->tempDir . '/vendor/horde';
        $configDir = $this->tempDir . '/config';
        mkdir($vendorDir . '/horde/config', 0o755, true);
        mkdir($configDir . '/horde', 0o755, true);

        file_put_contents(
            $vendorDir . '/horde/config/nls.php',
            <<<'PHP'
                <?php
                $horde_nls_config = [
                    'defaults' => ['language' => ''],
                    'languages' => [
                        'en_US' => 'English (American)',
                        'de_DE' => 'Deutsch',
                    ],
                    'aliases' => ['de' => 'de_DE'],
                ];
                PHP
        );

        $this->configLoader = new BackendConfigLoader($configDir, $vendorDir);

        $hordeSession = new HordeSession(new SessionId('test-session'));
        $this->sessionAccessor = new SessionAccessor();
        $this->sessionAccessor->replaceWith($hordeSession);
    }

    protected function tearDown(): void
    {
        $files = array_diff(scandir($this->tempDir . '/vendor/horde/horde/config'), ['.', '..']);
        foreach ($files as $file) {
            unlink($this->tempDir . '/vendor/horde/horde/config/' . $file);
        }
    }

    private function makeNlsconfig(): Nlsconfig
    {
        return new Nlsconfig($this->sessionAccessor, $this->configLoader);
    }

    public function testImplementsLanguageContext(): void
    {
        $this->assertInstanceOf(LanguageContext::class, $this->makeNlsconfig());
    }

    public function testValidLang(): void
    {
        $nls = $this->makeNlsconfig();
        $this->assertTrue($nls->validLang('en_US'));
        $this->assertFalse($nls->validLang('xy_XY'));
    }

    public function testPreferredLangFallsBackToDefaultWhenNothingElseMatches(): void
    {
        $nls = $this->makeNlsconfig();
        $this->assertSame('en_US', $nls->preferredLang());
    }

    public function testPreferredLangUsesExplicitLangArgument(): void
    {
        $nls = $this->makeNlsconfig();
        $this->assertSame('de_DE', $nls->preferredLang('de_DE'));
    }

    public function testSetLanguagePersistsToSessionAndGetLanguageReflectsIt(): void
    {
        $nls = $this->makeNlsconfig();
        $this->assertSame('de_DE', $nls->setLanguage('de_DE'));
        $this->assertSame('de_DE', $nls->getLanguage());
        $this->assertSame('de_DE', $this->sessionAccessor->getScoped('horde', 'language'));
    }

    public function testPreferredLangPrefersSessionOverExplicitLang(): void
    {
        $this->sessionAccessor->setScoped('horde', 'language', 'de_DE');
        $nls = $this->makeNlsconfig();
        $this->assertSame('de_DE', $nls->preferredLang('en_US'));
    }

    public function testGetLocaleIsAliasForGetLanguage(): void
    {
        $nls = $this->makeNlsconfig();
        $nls->setLanguage('de_DE');
        $this->assertSame($nls->getLanguage(), $nls->getLocale());
    }
}
