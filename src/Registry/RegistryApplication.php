<?php

namespace Horde\Core\Registry;

/**
 * RegistryApplication represents an application's variable paths and uris from registry config.
 *
 * Items not covered:
 * - staticfs and staticuri are handled as a Service instead
 * - menuparent is strictly a grouping and display issue
 * - properties for heading and website entries
 * - API Provides are handled as separate entities
 * - initialPage is deprecated
 * - $app_fileroot and $app_webroot are no longer supported.
 * - installation root dir, installation web root dir, default webroot, vendor root dir, autoloader path are not application specific and handled by RegistryConfig directly
 */
class RegistryApplication
{
    public function __construct(
        protected string $name,
        protected string $webroot,
        protected string $fileroot = '',
        // Where configs are read and written
        protected string $varConfigDir = '',
        // Config defaults shipped with the application, not writable
        protected string $vendorConfigDir = 'config/',
        protected string $jsFs = '',
        protected string $jsUri = '',
        protected string $themesFs = '',
        protected string $themesUri = '',
        protected string $status = 'active',
        protected string $vendorName = 'horde',
        protected string $templatesFs = 'templates/',
        // Only used for overrides, otherwise let the theme handle it
        protected string $iconUri = '',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }
    public function getWebroot(): string
    {
        return $this->webroot;
    }
    public function getFileroot(): string
    {
        return $this->fileroot;
    }
    public function getVarConfigDir(): string
    {
        return $this->varConfigDir;
    }
    public function getVendorConfigDir(): string
    {
        return $this->vendorConfigDir;
    }
    public function getJsFs(): string
    {
        return $this->jsFs;
    }
    public function getJsUri(): string
    {
        return $this->jsUri;
    }
    public function getThemesFs(): string
    {
        return $this->themesFs;
    }
    public function getThemesUri(): string
    {
        return $this->themesUri;
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function getVendorName(): string
    {
        return $this->vendorName;
    }
    public function getTemplatesFs(): string
    {
        return $this->templatesFs;
    }
    public function getIconUri(): string
    {
        return $this->iconUri;
    }

    public function getVendorDirPath(): string
    {
        return $this->fileroot . DIRECTORY_SEPARATOR . $this->vendorName;
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'admin']);
    }
}
