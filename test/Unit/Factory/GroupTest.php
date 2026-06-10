<?php

declare(strict_types=1);

/**
 * Test the Group factory.
 *
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Test\Unit\Factory;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Horde_Core_Factory_Group;
use Horde_Injector;
use Horde_Injector_TopLevel;

#[CoversMethod(Horde_Core_Factory_Group::class, 'create')]
class GroupTest extends TestCase
{
    public function testMock()
    {
        $injector = new Horde_Injector(new Horde_Injector_TopLevel());
        $injector->bindFactory('Horde_Group', 'Horde_Core_Factory_Group', 'create');
        $GLOBALS['conf']['group']['driver'] = 'mock';
        $this->assertInstanceOf(
            'Horde_Group_Mock',
            $injector->getInstance('Horde_Group')
        );
    }
}
