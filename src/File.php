<?php

declare(strict_types=1);

namespace Horde\Core;

use Stringable;
use SplFileInfo;
use SplFileObject;

/**
 * Base class for a stringable, file path
 */
class File implements Stringable
{
    use FileTrait;
}
