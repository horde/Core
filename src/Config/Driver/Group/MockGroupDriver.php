<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Config\Driver\Group;

use Horde\Core\Config\Driver\DriverAvailability;
use Horde\Core\Config\Driver\DriverInterface;
use Horde\Core\Config\Metadata\ValidationResult;

/**
 * Mock group driver configuration metadata.
 *
 * Dummy driver that provides no group functionality.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class MockGroupDriver implements DriverInterface
{
    public function getName(): string
    {
        return 'mock';
    }

    public function getDescription(): string
    {
        return 'No Groups';
    }

    public function getType(): string
    {
        return 'group';
    }

    public function getFields(): array
    {
        return [];
    }

    public function validate(array $config): ValidationResult
    {
        return new ValidationResult([]);
    }

    public function checkAvailability(): DriverAvailability
    {
        return DriverAvailability::available();
    }
}
