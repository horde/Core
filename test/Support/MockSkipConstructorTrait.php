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

namespace Horde\Core\Test\Support;

use PHPUnit\Framework\MockObject\MockObject;

/**
 * Helper for mocking classes whose constructors are inconvenient
 * to satisfy in a unit test (private, expensive, or with hard-to-fake
 * dependencies).
 *
 * Replaces the same-named helper from horde/test so unit tests no
 * longer extend Horde\Test\TestCase and can build directly on
 * PHPUnit\Framework\TestCase.
 */
trait MockSkipConstructorTrait
{
    /**
     * Build a mock for $className without invoking its constructor.
     *
     * @param class-string $className     Fully qualified class name.
     * @param string[] $methods           Methods to mock; others remain real
     *                                    (matches MockBuilder::onlyMethods()).
     * @param array $arguments            Constructor arguments. Only used when
     *                                    a constructor invocation IS desired.
     * @param string $mockClassName       Optional explicit class name for
     *                                    the generated mock.
     */
    public function getMockSkipConstructor(
        string $className,
        array $methods = [],
        array $arguments = [],
        string $mockClassName = '',
    ): MockObject {
        $builder = $this->getMockBuilder($className)->disableOriginalConstructor();

        if ($methods !== []) {
            $builder = $builder->onlyMethods($methods);
        }
        if ($arguments !== []) {
            $builder = $builder->setConstructorArgs($arguments);
        }
        if ($mockClassName !== '') {
            $builder = $builder->setMockClassName($mockClassName);
        }

        return $builder->getMock();
    }
}
