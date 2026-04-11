<?php

declare(strict_types=1);

namespace Horde\Core;

use Stringable;
use SplFileInfo;
use SplFileObject;

/**
 * Base class for a stringable, file path
 */
trait FileTrait
{
    private string $path;
    public function __construct(
        string|Stringable $path,
    ) {
        // Flatten Stringables to strings, we want to decouple our value from other objects.
        $this->path = (string) $path;
    }

    /**
     * True if the deployment root is a directory or a symlink to a directory.
     */
    public function exists(): bool
    {
        return is_file($this->path);
    }

    /**
     * True if the deployment root exists as a directory or symlink to a directory and is readable.
     *
     * For any further operations defer to the getDirectoryIterator and getSplFileInfo methods.
     */
    public function isReadable(): bool
    {
        return $this->exists() && is_readable($this->path);
    }

    public function getSplFileInfo(): SplFileInfo
    {
        return new SplFileInfo($this->path);
    }

    public function getSplFileObject(): SplFileObject
    {
        return new SplFileObject($this->path);
    }

    public function __toString(): string
    {
        return $this->path;
    }
}
