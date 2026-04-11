<?php

declare(strict_types=1);

namespace Horde\Core\Config;

use Horde\Core\Directory;

/**
 * An application's directory in the vendor dir. Usually $deployment/vendor/$vendor/$application.
 */
class ApplicationVendorDirectory extends Directory
{
    /**
     * If the vendor is horde, this is $deployment/vendor/horde
     *
     * In theory registry allows to configure-in applications that are outside
     * a common root project but this most likely involves additional efforts like a custom autoloader.
     */
    public function getOurVendorDirectory(): Directory
    {
        return new Directory(dirname((string) $this, 1));
    }

    /**
     * Except in VERY strange cases, this should be $deployment/vendor/
     */
    public function getTopVendorDirectory(): Directory
    {
        return new Directory(dirname((string) $this, 2));
    }
}
