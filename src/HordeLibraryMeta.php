<?php
namespace Horde\Core;
use Horde\Composer\ComposerJsonFile;
use Horde\HordeYmlFile\HordeYmlFile;

/**
 * A HordeLibrary has any of
 * - a horde format schema file
 * - a js/ dir which needs to be installed into the webroot
 */
class HordeLibraryMeta {

    public readonly bool $hasDbMigration;
    public readonly bool $hasJsDir;
    public readonly bool $hasBinDir;
    public readonly HordeYmlFile $hordeyml;
    public readonly ComposerJsonFile $composerJson;

    /**
     * @param string $vendorDirPath
     *   The path to the vendor directory of the library.
     */
    public function __construct(
        public readonly string $vendorDirPath,
    ) {
        $this->hordeyml         = new HordeYmlFile($this->vendorDirPath . '/.horde.yml');
        $this->composerJson     = new ComposerJsonFile($this->vendorDirPath . '/composer.json');
        $this->hasDbMigration   = is_dir($this->vendorDirPath . '/migration');
        $this->hasJsDir         = is_dir($this->vendorDirPath . '/js');
        $this->hasBinDir        = is_dir($this->vendorDirPath . '/bin');
    }
}