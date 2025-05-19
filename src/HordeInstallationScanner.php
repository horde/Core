<?php
namespace Horde\Core;
use Stringable;
use Horde\Composer\ComposerRootProject;
// Scan a filesystem layout to identify installed Horde applications, themes and libraries
class HordeInstallationScanner
{
    private string $baseDir;
    private ComposerRootProject $composerRootProject;
    private array $hordeApplications = [];
    public function __construct(
        string|Stringable $baseDir
    ) {
        $this->baseDir = (string) $baseDir;
        if (class_exists('\Composer\InstalledVersions')) {
            
            foreach (\Composer\InstalledVersions::getInstalledPackagesByType('horde-application') as $package) {
                $this->hordeApplications[] = new HordeApplicationMeta(
                    \Composer\InstalledVersions::getInstallPath($package)
                );
            }
            foreach (\Composer\InstalledVersions::getInstalledPackagesByType('horde-library') as $package) {
                $this->hordeApplications[] = new HordeLibraryMeta(
                    \Composer\InstalledVersions::getInstallPath($package)
                );
            }
        }
        $this->composerRootProject = new ComposerRootProject($this->baseDir);
    }
}