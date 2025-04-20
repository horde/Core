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
     * Composer instance
     *
     * @var Composer
     */
    protected $composer;

    /**
     * Repository manager
     *
     * @var RepositoryManager
     */
    protected $repositoryManager;

    /**
     * Constructor
     *
     * @throws \RuntimeException if Composer cannot be initialized
     */
    public function __construct()
    {
        try {
            $this->composer = \Composer\Factory::create(new \Composer\IO\NullIO());
            $this->repositoryManager = $this->composer->getRepositoryManager();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to initialize Composer: ' . $e->getMessage());
        }
    }

    /**
     * Get available versions for a package
     *
     * @param string $packageName Package name (e.g., 'horde/core')
     * @return array Array of available versions
     */
    public function getAvailableVersions($packageName)
    {
        $versions = array();
        
        foreach ($this->repositoryManager->getRepositories() as $repository) {
            if ($repository instanceof \Composer\Repository\RepositoryInterface) {
                $packages = $repository->findPackages($packageName);
                foreach ($packages as $package) {
                    $versions[] = $package->getVersion();
                }
            }
        }

        // Sort versions in descending order
        usort($versions, 'version_compare');
        $versions = array_reverse($versions);

        return $versions;
    }

    /**
     * Get installed version of a package
     *
     * @param string $packageName Package name (e.g., 'horde/core')
     * @return string|null Installed version or null if not found
     */
    public function getInstalledVersion($packageName)
    {
        $localRepository = $this->composer->getRepositoryManager()->getLocalRepository();
        $packages = $localRepository->findPackages($packageName);
        
        if (!empty($packages)) {
            return $packages[0]->getVersion();
        }
        
        return null;
    }

    /**
     * Check if a newer version is available
     *
     * @param string $packageName Package name (e.g., 'horde/core')
     * @return array|null Array with version info or null if no update available
     */
    public function checkForUpdate($packageName)
    {
        $installedVersion = $this->getInstalledVersion($packageName);
        if (!$installedVersion) {
            return null;
        }

        $availableVersions = $this->getAvailableVersions($packageName);
        if (empty($availableVersions)) {
            return null;
        }

        $latestVersion = $availableVersions[0];
        if (version_compare($latestVersion, $installedVersion, '>')) {
            return array(
                'current' => $installedVersion,
                'latest' => $latestVersion,
                'update_available' => true
            );
        }

        return null;
    }

    /**
     * Check for updates for all Horde packages
     *
     * @return array Array of packages with available updates
     */
    public function checkAllHordePackages()
    {
        $updates = array();
        $localRepository = $this->composer->getRepositoryManager()->getLocalRepository();
        
        foreach ($localRepository->getPackages() as $package) {
            if (strpos($package->getName(), 'horde/') === 0) {
                $updateInfo = $this->checkForUpdate($package->getName());
                if ($updateInfo) {
                    $updates[$package->getName()] = $updateInfo;
                }
            }
        }

        return $updates;
    }
} 
