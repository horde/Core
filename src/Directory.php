<?php

declare(strict_types=1);

namespace Horde\Core;

use Stringable;
use Iterator;
use DirectoryIterator;
use SplFileInfo;
use SplFileObject;
use RecursiveDirectoryIterator;
use FilesystemIterator;
use RuntimeException;

/**
 * Base class for a stringable, iterable directory paths to be extended into Injectables.
 */
class Directory implements Stringable
{
    use FileTrait;

    /**
     * True if the deployment root is a directory or a symlink to a directory.
     */
    public function exists(): bool
    {
        return is_dir($this->path);
    }

    /**
     * Return a RecursiveDirectoryIterator for the directory and its subdirectories.
     */
    public function getRecursiveDirectoryIterator(): RecursiveDirectoryIterator
    {
        if ($this->isReadable()) {
            return new \RecursiveDirectoryIterator($this->path);
        }
        throw new RuntimeException("Directory is not readable: {$this->path}");
    }

    /**
     * Return a flat DirectoryIterator for the directory.
     */
    public function getDirectoryIterator(): Iterator
    {
        if ($this->isReadable()) {
            return new DirectoryIterator($this->path);
        }
        throw new \RuntimeException("Directory is not readable: {$this->path}");
    }

    public function getFilesystemIterator(): FilesystemIterator
    {
        if ($this->isReadable()) {
            return new FilesystemIterator($this->path);
        }
        throw new RuntimeException("Directory is not readable: {$this->path}");
    }

}
