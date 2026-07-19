<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author Torben Dannhauer <torben@dannhauer.de>
 * @category Horde
 * @copyright 2026 The Horde Project
 * @license http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package Core
 * @subpackage UnitTests
 */

namespace Horde\Core\Test\Unit\ActiveSync;

use Horde_Core_ActiveSync_Connector;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Horde_Core_ActiveSync_Connector contacts helpers without Turba.
 */
#[CoversMethod(Horde_Core_ActiveSync_Connector::class, 'contacts_getGal')]
#[CoversMethod(Horde_Core_ActiveSync_Connector::class, 'contacts_search')]
#[CoversMethod(Horde_Core_ActiveSync_Connector::class, 'resolveRecipient')]
class ConnectorContactsGetGalTest extends TestCase
{
    public function testContactsGetGalReturnsFalseWhenMethodMissing(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->once())
            ->method('hasMethod')
            ->with('contacts/getGalUid')
            ->willReturn(false);
        $registry->expects($this->never())
            ->method('__get');

        $connector = new Horde_Core_ActiveSync_Connector([
            'registry' => $registry,
        ]);

        $this->assertFalse($connector->contacts_getGal());
        // Cached: second call must not hit the registry again.
        $this->assertFalse($connector->contacts_getGal());
    }

    public function testContactsGetGalReturnsUidWhenAvailable(): void
    {
        $contacts = new class () {
            public function getGalUid()
            {
                return 'gal-source';
            }
        };

        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->once())
            ->method('hasMethod')
            ->with('contacts/getGalUid')
            ->willReturn(true);
        $registry->expects($this->once())
            ->method('__get')
            ->with('contacts')
            ->willReturn($contacts);

        $connector = new Horde_Core_ActiveSync_Connector([
            'registry' => $registry,
        ]);

        $this->assertSame('gal-source', $connector->contacts_getGal());
        $this->assertSame('gal-source', $connector->contacts_getGal());
    }

    public function testContactsSearchReturnsEmptyWithoutContactsInterface(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->once())
            ->method('hasInterface')
            ->with('contacts')
            ->willReturn(false);
        $registry->expects($this->never())
            ->method('hasMethod');
        $registry->expects($this->never())
            ->method('__get');

        $connector = new Horde_Core_ActiveSync_Connector([
            'registry' => $registry,
        ]);

        $this->assertSame([], $connector->contacts_search('alice'));
    }

    public function testResolveRecipientReturnsEmptyWithoutContactsInterface(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->once())
            ->method('hasInterface')
            ->with('contacts')
            ->willReturn(false);
        $registry->expects($this->never())
            ->method('__get');

        $connector = new Horde_Core_ActiveSync_Connector([
            'registry' => $registry,
        ]);

        $this->assertSame(
            ['alice@example.com' => []],
            $connector->resolveRecipient('alice@example.com')
        );
    }
}
