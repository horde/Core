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
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * IO instance
     *
     * @var \Composer\IO\NullIO
     */
    protected $io;

    /**
     * Config instance
     *
     * @var \Composer\Config
     */
    protected $config;

    /**
     * Process executor instance
     *
     * @var \Composer\Util\ProcessExecutor
     */
    protected $process;

    /**
     * HTTP downloader instance
     *
     * @var \Composer\Util\HttpDownloader
     */
    protected $httpDownloader;

    /**
     * Event dispatcher instance
     *
     * @var \Composer\EventDispatcher\EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * Repository manager
     *
     * @var RepositoryManager
     */
    protected $repositoryManager;

    /**
     * Root directory of horde installation 
     *
     * @var string
     */
    protected $rootDir;

    /**
     * Installed packages data
     *
     * @var array
     */
    protected $installedPackages;

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
        $this->rootDir = dirname(dirname(dirname(dirname(dirname(HORDE_BASE)))));
        Horde::Log('Root directory: ' . $this->rootDir, 'ERR');
        $composerFile = $this->rootDir . '/composer.json';
        
        if (!file_exists($composerFile)) {
            throw new Horde_Exception('Could not find composer.json in ' . $this->rootDir);
        }

        // Load installed.json
        $installedPath = $this->rootDir . '/vendor/composer/installed.json';
        if (!file_exists($installedPath)) {
            throw new Horde_Exception('Could not find installed.json at ' . $installedPath);
        }

        $installedData = json_decode(file_get_contents($installedPath), true);
        if ($installedData === null) {
            throw new Horde_Exception('Could not parse installed.json at ' . $installedPath);
        }

        $this->installedPackages = $installedData['packages'] ?? $installedData;
        if (!is_array($this->installedPackages)) {
            throw new Horde_Exception('Invalid format in installed.json at ' . $installedPath);
        }
        
        try {
            // Create Composer instance with proper working directory
            $this->io = new \Composer\IO\NullIO();
            $this->config = \Composer\Factory::createConfig($this->io, $this->rootDir);
            $this->composer = \Composer\Factory::create($this->io, $composerFile, false, $this->rootDir, $this->config);
            $this->repositoryManager = $this->composer->getRepositoryManager();
            
            // Initialize other Composer components
            $this->process = new \Composer\Util\ProcessExecutor($this->io);
            $this->httpDownloader = new \Composer\Util\HttpDownloader($this->io, $this->config);
            $this->eventDispatcher = new \Composer\EventDispatcher\EventDispatcher($this->composer, $this->io);
        } catch (\Exception $e) {
            throw new Horde_Exception('Failed to initialize Composer: ' . $e->getMessage());
        }
    }

    /**
     * Get the latest commit hash for a given branch
     * 
     * This function is used to get the latest commit hash for a given branch
     * 
     * Attention: It uses composers internal classes and methods.
     * These are not part of the public API, but quite stable.
     *
     * @param string $gitUrl The URL of the Git repository
     * @param string $branch The branch name
     * @return string|null The latest commit hash or null if not found
     */
    protected function getLatestCommitHash(string $gitUrl, string $branch): ?string
    {
        // Create a VCS repository object pointing to the Git URL
        $repo = new \Composer\Repository\VcsRepository(
            [
                'url' => $gitUrl,
                'type' => 'git',
            ],
            $this->io,
            $this->config,
            $this->httpDownloader,
            $this->eventDispatcher,
            $this->process
        );

        if(!$repo instanceof \Composer\Repository\VcsRepository) {
            Horde::Log('Repository is not a VCS repository: ' . $gitUrl, 'ERR');
            return null;
        }
      
        // Get the Git driver for the remote repository
        $driver = $repo->getDriver();
        if (!$driver instanceof \Composer\Repository\Vcs\GitDriver) {
            Horde::Log('Repository is not a Git repository: ' . $gitUrl, 'ERR');
            return null;
        }

        try {
            // Get all remote branches and their commit hashes
            $remoteBranches = $driver->getBranches();
            //Horde::Log('Remote-Branches for git-url:' . $gitUrl . ' =' . print_r($remoteBranches, true), 'ERR');

            if (empty($remoteBranches)) {
                Horde::Log('No branches found for remote repository: ' . $gitUrl, 'ERR');
                return null;
            }

            // Get the latest commit for our branch
            if (!isset($remoteBranches[$branch])) {
                Horde::Log('Branch ' . $branch . ' not found in remote repository', 'ERR');
                return null;
            }

            return $remoteBranches[$branch];

        } catch (\Exception $e) {
            Horde::Log('Error fetching branches for ' . $gitUrl . ': ' . $e->getMessage(), 'ERR');
            return null;
        }
    }


    /**
     * Get available versions for a package from its installation repository
     *
     * This function:
     *  - Finds the installed package and its source repository
     *  - Queries that repository for all available versions
     *
     * @param string $packageName Package name (e.g., 'horde/core')
     * @return array Sorted list of available versions
     */
    public function getAvailableVersions(string $packageName): array
    {
        //Horde::Log('Getting available versions for ' . $packageName, 'ERR');
        if (!\Composer\InstalledVersions::isInstalled($packageName)) {
            Horde::Log('Package not installed: ' . $packageName, 'ERR');
            return [];
        }

        // Find the package entry from locally installed packages
        $packageEntry = null;
        foreach ($this->installedPackages as $pkg) {
            if ($pkg['name'] === $packageName) {
                $packageEntry = $pkg;
                break;
            }
        }

        if (!$packageEntry || !isset($packageEntry['source'])) {
            Horde::Log('Could not find source information for ' . $packageName, 'ERR');
            return [];
        }

        $sourceUrl = $packageEntry['source']['url'] ?? null;
        $sourceType = $packageEntry['source']['type'] ?? null;
        $sourceReference = $packageEntry['source']['reference'] ?? null;

        if ($sourceType === 'git') {
            // For Git repositories, identify the local branch from version string
            $version = $packageEntry['version'] ?? '';
            $localBranch = null;
            
            // Strip away the dev- prefix or -dev suffix
            if (strpos($version, 'dev-') === 0) {
                $localBranch = substr($version, 4);
            } elseif (strpos($version, '-dev') !== false) {
                $localBranch = substr($version, 0, -4);
            }
            
            //Horde::Log('Package ' . $packageName . ' :: version: ' . $version . ' -> branch: ' . ($localBranch ?? 'unknown'), 'ERR');

            if ($localBranch === null) {
                Horde::Log('Could not identify branch from version: ' . $version, 'ERR');
                return [];
            }

            // Get the latest commit hash for our branch
            $latestRemoteRef = $this->getLatestCommitHash($sourceUrl, $localBranch);
            if ($latestRemoteRef === null) {
                return [];
            }

            // If we have a different reference, there's an update available
            if ($latestRemoteRef !== $sourceReference) {
                Horde::Log('Update available for ' . $packageName . ': ' . $sourceReference . ' -> ' . $latestRemoteRef, 'ERR');
                return [$latestRemoteRef];
            }
            return [];

        } elseif ($sourceType === 'composer') {
            
            $repoConfig = [
                'url'  => $sourceUrl,
                'type' => 'composer',
            ];

            $repo = new \Composer\Repository\ComposerRepository(
                $repoConfig,
                $this->io,
                $this->config,
                $this->httpDownloader,
                $this->eventDispatcher,
                $this->process
            );

            // For Composer repositories, use standard version comparison
            $versions = [];
            try {
                $packages = $repo->findPackages($packageName);
                foreach ($packages as $package) {
                    $versions[] = $package->getVersion();
                }
            } catch (\Exception $e) {
                Horde::Log('Error fetching versions for ' . $packageName . ': ' . $e->getMessage(), 'ERR');
                return [];
            }

            $versions = array_unique($versions);
            usort($versions, 'version_compare');
            return array_reverse($versions);
        }

        return [];
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

            Horde::Log('Available versions for ' . $packageName . ': ' . print_r($availableVersions, true), 'ERR');
            $latestVersion = $availableVersions[0];
            if (version_compare($latestVersion, $installedVersion, '>')) {
                Horde::Log('Update available for ' . $packageName . ': ' . $installedVersion . ' -> ' . $latestVersion, 'ERR');
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
