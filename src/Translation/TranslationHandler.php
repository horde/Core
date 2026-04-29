<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Translation;

use Horde\Translation\Handler;

/**
 * Extended translation handler interface for application-level use.
 *
 * Adds the _() convenience method (legacy gettext alias) on top of
 * the base Handler interface (t, ngettext, format).
 *
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
interface TranslationHandler extends Handler
{
    /**
     * Legacy convenience alias for t().
     *
     * Allows namespaced application code to define a namespace-level
     * _() function that delegates here, shadowing PHP's built-in
     * gettext _() without any extension tricks.
     *
     * @param string $message  The string to translate.
     *
     * @return string  The translated string, or the original if no
     *                 translation exists.
     */
    public function _(string $message): string;
}
