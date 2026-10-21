<?php

declare(strict_types=1);

/**
 * Tests for Horde_Themes_Css_Cache.
 *
 * @category Horde
 * @package  Core
 */

namespace Horde\Core\Test\Unit\Themes\Css;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde_Registry;
use Horde_Themes_Css_Cache;

#[CoversClass(Horde_Themes_Css_Cache::class)]
class CacheTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['registry'] = $this->createStub(Horde_Registry::class);
        $GLOBALS['registry']->method('get')->willReturn('/horde');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['registry']);
    }

    private function getCache(): Horde_Themes_Css_Cache
    {
        return new class extends Horde_Themes_Css_Cache {
            public function process($css, $cacheid)
            {
                return $this->_splitExternal($css);
            }
        };
    }

    public function testSplitExternal(): void
    {
        $cdn = 'https://cdn.example.org/styles/main.css';
        $local = ['app' => 'horde', 'fs' => '/srv/themes/x/screen.css', 'uri' => '/themes/x/screen.css'];

        [$external, $rest] = $this->getCache()->process([
            ['app' => 'horde', 'fs' => $cdn, 'uri' => $cdn],
            $local,
        ], '');

        $this->assertCount(1, $external);
        $this->assertSame($cdn, $external[0]->toString(false, true));
        $this->assertSame([$local], $rest);
    }

    public function testSplitExternalWithoutExternal(): void
    {
        $local = ['app' => 'horde', 'fs' => '/srv/themes/x/screen.css', 'uri' => '/themes/x/screen.css'];

        [$external, $rest] = $this->getCache()->process([$local], '');

        $this->assertSame([], $external);
        $this->assertSame([$local], $rest);
    }
}
