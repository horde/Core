<?php
/**
 * Copyright 2025 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author   Torben Dannhauer <torben+horde@dannhauer.de>
 * @category Horde
 * @package  Core
 */

class Horde_Core_VersionChecker
{
    /**
     * Repository manager
     *
     * @var RepositoryManager
     */
    protected $repositoryManager;

    /**
     * Constructor
     *
     * @throws Horde_Exception if Composer cannot be initialized
     */
    public function __construct()
    {
        if (!defined('HORDE_BASE')) {
            throw new Horde_Exception('HORDE_BASE is not defined');
        }
        
        // Get the root directory by going up from HORDE_BASE
        $rootDir = dirname(dirname(HORDE_BASE));
        $composerFile = $rootDir . '/composer.json';
        
        if (!file_exists($composerFile)) {
            throw new Horde_Exception('Could not find composer.json in ' . $rootDir);
        }
        
        try {
            // Create Composer instance with proper working directory
            $io = new \Composer\IO\NullIO();
            $config = \Composer\Factory::createConfig($io, $rootDir);
            $composer = \Composer\Factory::create($io, $composerFile, false, $rootDir, $config);
            $this->repositoryManager = $composer->getRepositoryManager();
        } catch (\Exception $e) {
            throw new Horde_Exception('Failed to initialize Composer: ' . $e->getMessage());
        }
    }

    /**
     * Get available versions for a package
     *
     * @param string $packageName Package name (e.g., 'horde/core')
     * @return array Array of available versions
     */
    public function getAvailableVersions(string $packageName) : array
    {
        $versions = [];
        
        try {
            // Get all defined repositories (Packagist, GitHub, etc.)
            $repositories = $this->repositoryManager->getRepositories();
        
            foreach ($repositories as $repo) {
                if (!method_exists($repo, 'findPackages')) {
                    Horde::Log('Repository ' . get_class($repo) . ' does not support findPackages', 'DEBUG');
                    continue;
                }
        
                try {
                    $packages = $repo->findPackages($packageName);
        
                    foreach ($packages as $package) {
                        $versions[] = $package->getVersion(); // Normalized version
                    }
                } catch (\Exception $e) {
                    // Log but continue with other repositories
                    Horde::Log('Error checking repository for ' . $packageName . ': ' . $e->getMessage(), 'ERR');
                    continue;
                }
            }
        } catch (\Exception $e) {
            Horde::Log('Error getting available versions for ' . $packageName . ': ' . $e->getMessage(), 'ERR');
            return [];
        }
    
        // Remove duplicates and sort descending
        $versions = array_unique($versions);
        usort($versions, 'version_compare');

        return array_reverse($versions);
    }

    /**
     * Get installed version of a package
     *
     * @param string $packageName Package name (e.g., 'horde/core')
     * @return string|null Installed version or null if not found
     */
    public function getInstalledVersion(string $packageName) : string|null
    {
        // Check if the package is installed
        if (!\Composer\InstalledVersions::isInstalled($packageName)) {
            Horde::Log('Package not installed: ' . $packageName, 'ERR');
            return null;
        }

        $installedVersion = \Composer\InstalledVersions::getVersion($packageName); // normalized
        $prettyVersion    = \Composer\InstalledVersions::getPrettyVersion($packageName); // human-readable

        return $installedVersion;
    }

    /**
     * Check if a newer version of a package is available
     *
     * @param string $packageName Package name (e.g., 'horde/core')
     * @return array|null Array with version info or null if no update available
     */
    public function checkForUpdate(string $packageName) : array|null
    {
        try {
            $installedVersion = $this->getInstalledVersion($packageName);
            if (!$installedVersion) {
                Horde::Log('Package ' . $packageName . ' is not installed', 'DEBUG');
                return null;
            }

            $availableVersions = $this->getAvailableVersions($packageName);
            if (empty($availableVersions)) {
                Horde::Log('No available versions found for ' . $packageName, 'DEBUG');
                return null;
            }

            $latestVersion = $availableVersions[0];
            if (version_compare($latestVersion, $installedVersion, '>')) {
                Horde::Log('Update available for ' . $packageName . ': ' . $installedVersion . ' -> ' . $latestVersion, 'INFO');
                return array(
                    'current' => $installedVersion,
                    'latest' => $latestVersion,
                    'update_available' => true
                );
            }
        } catch (\Exception $e) {
            Horde::Log('Error checking for updates for ' . $packageName . ': ' . $e->getMessage(), 'ERR');
            return null;
        }

        return null;
    }

    /**
     * Check for updates for all Horde packages
     *
     * @return array Array of packages with available updates
     */
    public function checkAllHordePackages() : array
    {
        $updates = array();
        
        try {
            // Get all installed packages
            foreach (\Composer\InstalledVersions::getInstalledPackages() as $packageName) {
                // Only check Horde packages
                if (strpos($packageName, 'horde/') !== 0) {
                    continue;
                }

                try {
                    $updateInfo = $this->checkForUpdate($packageName);
                    if ($updateInfo) {
                        $updates[$packageName] = $updateInfo;
                    }
                } catch (\Exception $e) {
                    Horde::Log('Error checking updates for ' . $packageName . ': ' . $e->getMessage(), 'ERR');
                    continue;
                }
            }
        } catch (\Exception $e) {
            Horde::Log('Error checking all Horde packages: ' . $e->getMessage(), 'ERR');
            return [];
        }

        Horde::Log('Found ' . count($updates) . ' packages with available updates', 'INFO');
        return $updates;
    }
}
