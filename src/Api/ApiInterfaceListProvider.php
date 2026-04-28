<?php

declare(strict_types=1);

namespace Horde\Core\Api;

use Horde\Rpc\Dispatch\ApiProvider;

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */
interface ApiInterfaceListProvider
{
    /**
     * @return array<string, class-string<ApiProvider>>
     */
    public function getApiInterfaceList(): array;
}
