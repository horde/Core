<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author     Torben Dannhauer <torben@dannhauer.de>
 * @category   Horde
 * @copyright  2026 The Horde Project
 * @license    http://www.horde.org/licenses/lgpl21 LGPL
 * @package    Core
 * @subpackage UnitTests
 */

namespace Horde\Core\Test\Unit\ActiveSync;

use Horde_Core_ActiveSync_Imap_Factory;
use Horde_Exception;
use PHPUnit\Framework\TestCase;

class ImapFactoryMsgflagsTest extends TestCase
{
    private mixed $previousRegistry = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousRegistry = $GLOBALS['registry'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->previousRegistry === null) {
            unset($GLOBALS['registry']);
        } else {
            $GLOBALS['registry'] = $this->previousRegistry;
        }
        parent::tearDown();
    }

    public function testGetMsgFlagsReadsMailCapabilityAndCachesIt(): void
    {
        $registry = new ImapFactoryMsgflagsRegistry([
            'Important' => 'Important',
            'to_do' => 'To Do',
        ]);
        $GLOBALS['registry'] = $registry;
        $factory = new Horde_Core_ActiveSync_Imap_Factory();

        $expected = [
            'important' => 'Important',
            'to_do' => 'To Do',
        ];
        $this->assertSame($expected, $factory->getMsgFlags());
        $this->assertSame($expected, $factory->getMsgFlags());
        $this->assertSame(1, $registry->calls);
    }

    public function testGetMsgFlagsIsEmptyWhenCapabilityIsMissing(): void
    {
        $registry = new ImapFactoryMsgflagsRegistry(['lost' => 'Lost'], false);
        $GLOBALS['registry'] = $registry;
        $factory = new Horde_Core_ActiveSync_Imap_Factory();

        $this->assertSame([], $factory->getMsgFlags());
        $this->assertSame(0, $registry->calls);
    }

    public function testGetMsgFlagsIsEmptyWhenMailApiFails(): void
    {
        $registry = new ImapFactoryMsgflagsRegistry(new Horde_Exception('prefs unavailable'));
        $GLOBALS['registry'] = $registry;
        $factory = new Horde_Core_ActiveSync_Imap_Factory();

        $this->assertSame([], $factory->getMsgFlags());
        $this->assertSame([], $factory->getMsgFlags());
        $this->assertSame(1, $registry->calls);
    }

    public function testGetMsgFlagsDropsNonArrayResult(): void
    {
        $GLOBALS['registry'] = new ImapFactoryMsgflagsRegistry(null);
        $factory = new Horde_Core_ActiveSync_Imap_Factory();

        $this->assertSame([], $factory->getMsgFlags());
    }

    public function testGetMsgFlagsSkipsNonScalarEntries(): void
    {
        $GLOBALS['registry'] = new ImapFactoryMsgflagsRegistry([
            'keep' => 'Keep',
            'drop' => ['not', 'a', 'label'],
            '' => 'blank',
        ]);
        $factory = new Horde_Core_ActiveSync_Imap_Factory();

        $this->assertSame(['keep' => 'Keep'], $factory->getMsgFlags());
    }
}

/**
 * Registry stub for mail/msgflags.
 */
class ImapFactoryMsgflagsRegistry
{
    public int $calls = 0;

    /**
     * @param array<string, mixed>|Horde_Exception|null $result
     */
    public function __construct(private mixed $result, private bool $available = true)
    {
    }

    public function hasMethod(string $method, ?string $app = null): string|false
    {
        if ($method !== 'mail/msgflags' || !$this->available) {
            return false;
        }

        return 'imp';
    }

    /**
     * @param array<mixed> $args
     */
    public function call(string $method, array $args = []): mixed
    {
        $this->calls++;
        if ($this->result instanceof Horde_Exception) {
            throw $this->result;
        }

        return $this->result;
    }
}
