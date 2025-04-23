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

       //Horde::Log('\Composer\InstalledVersions::getAllRawData(): ' . print_r(\Composer\InstalledVersions::getAllRawData(), true), 'ERR');
        
        try {
            // Create Composer instance with proper working directory
            $this->io = new \Composer\IO\NullIO();
            $this->config = \Composer\Factory::createConfig($this->io, $this->rootDir);
            $this->composer = \Composer\Factory::create($this->io, $composerFile, false, $this->rootDir, $this->config);
            $this->repositoryManager = $this->composer->getRepositoryManager();
            $this->process = new \Composer\Util\ProcessExecutor($this->io);
            $this->httpDownloader = new \Composer\Util\HttpDownloader($this->io, $this->config);
            $this->eventDispatcher = new \Composer\EventDispatcher\EventDispatcher($this->composer, $this->io);
        } catch (\Exception $e) {
            throw new Horde_Exception('Failed to initialize Composer: ' . $e->getMessage());
        }
    }

    /**
     * Get the installed 'horde/*' packages. other packages are filtered out.
     *
     * This method is static to avoid creating a new instance of the VersionChecker class by performance reasons.
     * 
     * @return array[] Array with package names (without horde/ prefix) as keys and package info as values
     */
    public static function getInstalledPackages(): array
    {
        $packages = array();
        try {
            // Get all installed packages
            foreach (\Composer\InstalledVersions::getInstalledPackages() as $packageName) {

                Horde::Log('Installed package: ' . print_r($packageName, true), 'ERR');
                // Only check Horde packages
                if (str_starts_with($packageName, 'horde/')) {
                    $shortName = substr($packageName, 6); // Remove 'horde/' prefix
                    $packages[$shortName] = array(
                        'version' => \Composer\InstalledVersions::getVersion($packageName),
                        'prettyVersion' => \Composer\InstalledVersions::getPrettyVersion($packageName),
                        'url' => '', // We'll need to get this from composer.lock or installed.json
                        'commit-reference' => \Composer\InstalledVersions::getReference($packageName) ?? ''
                    );
                }
            }
        } catch (\Exception $e) {
            Horde::Log('Error checking all Horde packages: ' . $e->getMessage(), 'ERR');
            return [];
        }
        return $packages;
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
     * Get latest available version for a package from its installation repository
     *
     * This function:
     *  - Finds the installed package and its source repository
     *  - Queries that repository for latest available version
     * 
     * Returns an array with the following structure:
     * [
     *     'version' => string,        // Normalized version number (e.g., '1.2.3.0' or 'dev-master')
     *     'prettyVersion' => string,  // Human-readable version (e.g., '1.2.3' or 'dev-master')
     *     'url' => string,           // Source URL (GitHub for Horde packages, Packagist URL for others)
     *     'commit-reference' => string // Latest commit hash for dev versions, empty string for releases
     * ]
     * 
     * For Horde packages installed from git:
     * - version: dev-branchname
     * - prettyVersion: dev-branchname
     * - url: GitHub repository URL
     * - commit-reference: Latest commit hash for the branch
     * 
     * For Horde packages from Packagist:
     * - version: Normalized version number
     * - prettyVersion: Human-readable version
     * - url: Packagist URL
     * - commit-reference: Empty string
     * 
     * For non-Horde packages:
     * - version: Normalized version number
     * - prettyVersion: Human-readable version
     * - url: Packagist URL
     * - commit-reference: Empty string
     * 
     * @param string $packageName Package name 'vendor/package' (e.g., 'horde/core')
     * @return array|null Array with version information or null if package not found/error
     */
    public function getAvailableVersion(string $packageName): array|null
    {
        //Horde::Log('Getting available versions for ' . $packageName, 'ERR');

        // Find the package entry from locally installed packages
        if (!\Composer\InstalledVersions::isInstalled($packageName)) {
            Horde::Log('Package not installed: ' . $packageName, 'ERR');
            return null;
        }
        $packageEntry = null;
        foreach ($this->installedPackages as $pkg) {
            if ($pkg['name'] === $packageName) {
                $packageEntry = $pkg;
                break;
            }
        }
        if (!$packageEntry || !isset($packageEntry['source']) || !isset($packageEntry['source']['url']) ) {
            Horde::Log('Could not find source information for ' . $packageName, 'ERR');
            return null;
        }

        // Fetch informations from the repository
        $returnArray = array();
        
        // Handle Horde git installations  differently from other packages
        if (str_starts_with($packageName, 'horde/') 
            && (str_starts_with($packageEntry['version_normalized'], 'dev-') || str_ends_with($packageEntry['version_normalized'], '-dev'))) { // according composer naming convention: installed via composer from git branch
            
            // For Git repositories, identify the local branch from version string
            $version = $packageEntry['version_normalized'] ?? '';
            $localBranch = null;
            
            // Strip away the dev- prefix or -dev suffix
            if (strpos($version, 'dev-') === 0) {
                $localBranch = substr($version, 4);
            } elseif (strpos($version, '-dev') !== false) {
                $localBranch = substr($version, 0, -4);
            }
            
            if ($localBranch === null) {
                Horde::Log('Could not identify branch from version: ' . $version, 'ERR');
                return null;
            }

            // Get the latest commit hash for our branch
            $latestRemoteRef = $this->getLatestCommitHash($packageEntry['source']['url'], $localBranch);
            if ($latestRemoteRef === null) {
                return null;
            }

            $returnArray['version'] = $packageEntry['version_normalized'];
            $returnArray['prettyVersion'] = $packageEntry['version'];
            $returnArray['url'] = $packageEntry['source']['url'];
            $returnArray['commit-reference'] = $latestRemoteRef;

        } else {
            // For horde non dev and non-Horde packages, use Packagist
            try {
                // Use Packagist API to get latest version
                $packagistUrl = 'https://repo.packagist.org/p2/' . $packageName . '.json';
                $response = $this->httpDownloader->get($packagistUrl);
                $data = json_decode($response->getBody(), true);
                
                if (isset($data['packages'][$packageName][0])) {
                    $latestPackage = $data['packages'][$packageName][0];
                    $returnArray['version'] = $latestPackage['version_normalized'];
                    $returnArray['prettyVersion'] = $latestPackage['version'];
                    $returnArray['url'] = 'https://packagist.org/packages/' . $packageName;
                    $returnArray['commit-reference'] = '';
                } else {
                    Horde::Log('Could not find latest version for ' . $packageName, 'ERR');
                    return null;
                }
            } catch (\Exception $e) {
                Horde::Log('Error fetching Packagist data for ' . $packageName . ': ' . $e->getMessage(), 'ERR');
                return null;
            }
        }

        return $returnArray;
    }

    /**
     * Get installed version of a package
     *
     * Returns an array with the following structure:
     * [
     *     'version' => string,        // Normalized version number (e.g., '1.2.3.0' or 'dev-master')
     *     'prettyVersion' => string,  // Human-readable version (e.g., '1.2.3' or 'dev-master')
     *     'url' => string,           // Source URL (GitHub for Horde packages, Packagist URL for others)
     *     'commit-reference' => string // Latest commit hash for dev versions, empty string for releases
     * ]
     *
     * @param string $packageName Package name (e.g., 'horde/core')
     * @return array|null Array with version information or null if package not found
     */
    public function getInstalledVersion(string $packageName): array|null
    {
        // Check if the package is installed
        if (!\Composer\InstalledVersions::isInstalled($packageName)) {
            Horde::Log('Package not installed: ' . $packageName, 'ERR');
            return null;
        }

        // Find the package entry from installed packages
        $packageEntry = null;
        foreach ($this->installedPackages as $pkg) {
            if ($pkg['name'] === $packageName) {
                $packageEntry = $pkg;
                break;
            }
        }

        if (!$packageEntry) {
            Horde::Log('Could not find package entry for ' . $packageName, 'ERR');
            return null;
        }

        $returnArray = array();
        $returnArray['version'] = $packageEntry['version_normalized'];
        $returnArray['prettyVersion'] = $packageEntry['version'];

        // Set URL based on package type
        if (str_starts_with($packageName, 'horde/')) {
            $returnArray['url'] = $packageEntry['source']['url'] ?? 'https://github.com/' . $packageName;
        } else {
            $returnArray['url'] = 'https://packagist.org/packages/' . $packageName;
        }

        // Set commit reference if available
        $returnArray['commit-reference'] = $packageEntry['source']['reference'] ?? '';

        return $returnArray;
    }

    /**
     * Check if a newer version of a package is available
     *
     * Returns an array with update information if an update is available:
     * [
     *     'version' => string,        // Latest available version
     *     'prettyVersion' => string,  // Human-readable latest version
     *     'url' => string,           // URL to download/update
     *     'commit-reference' => string // Latest commit hash if applicable
     * ]
     *
     * @param string $packageName Package name (e.g., 'horde/core')
     * @return array|null Array with update information or null if no update available
     */
    public function checkForUpdate(string $packageName): array|null
    {
        $installedVersion = $this->getInstalledVersion($packageName);
        if (!$installedVersion) {
            Horde::Log('Package ' . $packageName . ' is not installed', 'DEBUG');
            return null;
        }
        
        $availableVersion = $this->getAvailableVersion($packageName);
        if (!$availableVersion) {
            Horde::Log('No available version found for ' . $packageName, 'DEBUG');
            return null;
        }

        Horde::Log('Checking update for ' . $packageName . ': ' . 
            $installedVersion['version'] . ' -> ' . $availableVersion['version'], 'DEBUG');

        // For dev versions, check commit reference
        if (str_starts_with($installedVersion['version'], 'dev-') && 
            !empty($installedVersion['commit-reference']) && 
            !empty($availableVersion['commit-reference'])) {
            if ($installedVersion['commit-reference'] !== $availableVersion['commit-reference']) {
                Horde::Log('Git update available for ' . $packageName . ': ' . 
                    substr($installedVersion['commit-reference'], 0, 7) . ' -> ' . 
                    substr($availableVersion['commit-reference'], 0, 7), 'DEBUG');
                return $availableVersion;
            }
            return null;
        }

        // For regular versions, compare version numbers
        if (version_compare($availableVersion['version'], $installedVersion['version'], '>')) {
            Horde::Log('Update available for ' . $packageName . ': ' . 
                $installedVersion['version'] . ' -> ' . $availableVersion['version'], 'DEBUG');
            return $availableVersion;
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

        return $updates;
    }
}
