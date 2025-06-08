<?php

namespace Horde\Core\Registry;

/**
 * Default registry loader.
 *
 * Different from Horde_Registry_Loadconfig, this class ignores the config file and always honors a matching
 * vhost registry file, if it exists.
 *
 *
 *
 * Assumptions:
 * - applications have already been installed by composer and prepared by the horde-installer-plugin
 * - horde base app with the default registry is assumed to be installed as vendor/horde/horde
 * - horde registry snippets have been placed in var/config/horde/horde/registry.d (if any)
 * - registry.local.php (if any) or registry-$fqdn.php (if any) have been placed in var/config/horde/horde
 */
class RegistryLoader
{
    /**
     * Public for legacy support
     */
    public array $applications = [];
    private array $services = [];
    private array $apis = [];
    private array $deployment = [];

    private string $varConfigPath = '';
    private string $webRootPath = '';

    public function __construct(
        private string $deploymentRootPath = '',
        private string $baseRegistryFilePath = 'vendor/horde/horde/config/registry.php',
        private string $fqdn = ''
    ) {
        if ($deploymentRootPath === '') {
            /* Try to autodetect by looking for the composer autoloader's file and
             assuming the deployment root is two directories above it.*/
            if (class_exists('\Composer\InstalledVersions')) {
                $rootPackage = \Composer\InstalledVersions::getRootPackage();
                $deploymentRootPath = $rootPackage['install_path'] ?? '';
            }
        }
        $this->varConfigPath = $deploymentRootPath . '/var/config/';
        // TODO: Alternative fallback method by assuming we are in vendor/horde/core/src dir below webroot
        $files = [
        ];
        if (strlen($baseRegistryFilePath) && $baseRegistryFilePath[0] !== '/') {
            $baseRegistryFilePath = $deploymentRootPath . DIRECTORY_SEPARATOR . $baseRegistryFilePath;
        }
        if (file_exists($baseRegistryFilePath)) {
            $files[] = $baseRegistryFilePath;
        }
        if (is_dir($deploymentRootPath . '/var/config/horde/horde/registry.d')) {
            $files = array_merge($files, glob($deploymentRootPath . '/var/config/horde/horde/registry.d/*.php'));
        }
        if (file_exists($deploymentRootPath . '/var/config/horde/horde/registry-$fqdn.php')) {
            $files[] = $deploymentRootPath . '/var/config/horde/horde/registry.local.php';
        }
        foreach ($files as $file) {
            // TODO: Protect by eval, give meaningful error message if file is not a valid PHP file
            include $file;
        }
        // Support classic approach
        if (empty($this->deployment['webrootfs'])) {
            if (isset($deployment_webroot)) {
                $this->deployment['webrootfs'] = $deployment_webroot;
            } else {
                // Fallback to default
                $this->deployment['webrootfs'] = $deploymentRootPath . '/web/';
            }
        }
        // Support classic approach
        if (empty($this->deployment['webrooturi'])) {
            if (isset($deployment_webroot)) {
                $this->deployment['webrooturi'] = $deployment_webroot;
            } else {
                // Fallback to default
                $this->deployment['webrooturi'] = "$fqdn" ?? '/';
            }
        }
    }

    public function getRegistryApplicationsArray(): array
    {
        return $this->applications;
    }
    public function getRegistryServicesArray(): array
    {
        return $this->services;
    }

    public function getDeploymentRootPath(): string
    {
        return $this->deployment['webrootfs'] ?? $this->deploymentRootPath;
    }
    public function getWebRootPath(): string
    {
        return $this->deployment['webrooturi'] ?? '';
    }
}
