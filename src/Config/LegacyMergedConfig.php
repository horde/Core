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

namespace Horde\Core\Config;

/**
 * Legacy merged config wrapper for injector binding.
 *
 * Represents the merged app+horde configuration from the legacy
 * Horde_Registry_Hordeconfig system. This allows State to remain
 * a generic immutable config container while providing a specific
 * type for the legacy merged config that can be bound to the injector.
 *
 * Bound to injector after importConfig() is called, capturing the
 * current application's merged configuration state.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class LegacyMergedConfig extends State
{
    // Inherits all functionality from State:
    // - Immutable config access
    // - Dot notation support via get()
    // - ArrayAccess for backward compatibility
    // - toArray() for extracting plain array
}
