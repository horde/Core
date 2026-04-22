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

namespace Horde\Core\Test\Unit\PageOutput;

use Horde\Core\PageOutput\ViewMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewMode::class)]
class ViewModeTest extends TestCase
{
    public function testBasicCaseValue(): void
    {
        $this->assertSame('basic', ViewMode::BASIC->value);
    }

    public function testDynamicCaseValue(): void
    {
        $this->assertSame('dynamic', ViewMode::DYNAMIC->value);
    }

    public function testFromBasicString(): void
    {
        $this->assertSame(ViewMode::BASIC, ViewMode::from('basic'));
    }

    public function testFromDynamicString(): void
    {
        $this->assertSame(ViewMode::DYNAMIC, ViewMode::from('dynamic'));
    }

    public function testTryFromInvalidReturnsNull(): void
    {
        $this->assertNull(ViewMode::tryFrom('smartmobile'));
    }

    public function testCaseCount(): void
    {
        $this->assertCount(2, ViewMode::cases());
    }
}
