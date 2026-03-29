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

namespace Horde\Core\Config\Driver;

/**
 * Driver availability status.
 *
 * Indicates whether a driver can be used and why it might be unavailable.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class DriverAvailability
{
    public function __construct(
        public readonly bool $available,
        public readonly ?string $reason = null,
    ) {
    }

    /**
     * Create an available status.
     */
    public static function available(): self
    {
        return new self(true);
    }

    /**
     * Create an unavailable status with reason.
     */
    public static function unavailable(string $reason): self
    {
        return new self(false, $reason);
    }
}
