<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\ActiveSync\Ops;

use Horde\Util\HordeString;

final class DeviceLogPathResolver
{
    public function __construct(
        private readonly ?string $loggingType,
        private readonly ?string $loggingPath
    ) {
    }

    public function resolve(string $deviceId): ?string
    {
        if ($this->loggingType !== 'perdevice'
            || $this->loggingPath === null
            || $this->loggingPath === '') {
            return null;
        }

        return rtrim($this->loggingPath, '/')
            . '/'
            . HordeString::upper($deviceId)
            . '.txt';
    }
}
