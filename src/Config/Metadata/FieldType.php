<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde.org Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde.org Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Config\Metadata;

/**
 * Enum of supported configuration field types.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde.org Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
enum FieldType: string
{
    case TEXT = 'text';
    case INTEGER = 'int';
    case BOOLEAN = 'boolean';
    case PASSWORD = 'password';
    case ENUM = 'enum';
    case MULTI_ENUM = 'multienum';
    case SWITCH = 'switch';
    case STRING_LIST = 'stringlist';
    case PHP_CODE = 'php';
}
