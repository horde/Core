<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */

namespace Horde\Core\Service\VersionCheck;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * Fetches available package versions from the Packagist v2 metadata API.
 *
 * Uses the per-package endpoint at repo.packagist.org which returns all
 * versions in a minified format. Since `version` and `version_normalized`
 * are always present in every entry, we do not need the metadata-minifier
 * library — we only read version strings.
 *
 * Results are cached via PSR-16 to avoid redundant HTTP requests.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Core
 */
class PackagistAvailableSource implements AvailableVersionSource
{
    /** Base URL for Packagist v2 metadata API. */
    private const BASE_URL = 'https://repo.packagist.org/p2/';

    /** Cache key prefix. */
    private const CACHE_PREFIX = 'version_check.';

    /**
     * @param ClientInterface         $httpClient     PSR-18 HTTP client
     * @param RequestFactoryInterface $requestFactory PSR-17 request factory
     * @param CacheInterface          $cache          PSR-16 cache for storing results
     * @param int                     $ttl            Cache TTL in seconds (default: 24 hours)
     * @param string                  $minimumStability Minimum stability to consider
     *                                                  ("stable", "RC", "beta", "alpha", "dev")
     */
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly CacheInterface $cache,
        private readonly int $ttl = 86400,
        private readonly string $minimumStability = 'stable',
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getAvailableVersions(array $packageNames, bool $cacheOnly = false): array
    {
        $results = [];
        foreach ($packageNames as $name) {
            $info = $this->getPackageInfo($name, $cacheOnly);
            if ($info !== null) {
                $results[$name] = $info;
            }
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function getPackageInfo(string $packageName, bool $cacheOnly = false): ?VersionInfo
    {
        $cacheKey = self::CACHE_PREFIX . str_replace('/', '.', $packageName);

        $cached = $this->cache->get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            return new VersionInfo(
                packageName: $cached['packageName'],
                version: $cached['version'],
                versionNormalized: $cached['versionNormalized'],
                releaseDate: $cached['releaseDate'] ?? '',
                url: $cached['url'] ?? '',
            );
        }

        if ($cacheOnly) {
            return null;
        }

        try {
            $info = $this->fetchFromPackagist($packageName);
        } catch (Throwable) {
            return null;
        }

        if ($info === null) {
            return null;
        }

        $this->cache->set($cacheKey, [
            'packageName' => $info->packageName,
            'version' => $info->version,
            'versionNormalized' => $info->versionNormalized,
            'releaseDate' => $info->releaseDate,
            'url' => $info->url,
        ], $this->ttl);

        return $info;
    }

    /**
     * Fetch version data from the Packagist v2 metadata API.
     *
     * @param string $packageName Composer package name (e.g. "horde/core")
     *
     * @return VersionInfo|null Null if the package was not found or has no qualifying versions
     */
    private function fetchFromPackagist(string $packageName): ?VersionInfo
    {
        $url = self::BASE_URL . $packageName . '.json';
        $request = $this->requestFactory->createRequest('GET', $url);
        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['packages'][$packageName])) {
            return null;
        }

        return $this->findLatestStable($packageName, $data['packages'][$packageName]);
    }

    /**
     * Find the latest version that meets the minimum stability requirement.
     *
     * The versions array from Packagist v2 is ordered newest-first.
     * Each entry always contains `version` and `version_normalized`.
     *
     * @param string $packageName Package name for the VersionInfo result
     * @param array  $versions    Array of version entries from Packagist
     *
     * @return VersionInfo|null Null if no qualifying version was found
     */
    private function findLatestStable(string $packageName, array $versions): ?VersionInfo
    {
        foreach ($versions as $entry) {
            $normalized = $entry['version_normalized'] ?? '';
            $version = $entry['version'] ?? '';

            if ($normalized === '' || $version === '') {
                continue;
            }

            // Skip dev branches (e.g. "dev-FRAMEWORK_6_0")
            if (str_starts_with($version, 'dev-') || str_contains($normalized, 'dev')) {
                continue;
            }

            if (!$this->meetsStability($normalized)) {
                continue;
            }

            return new VersionInfo(
                packageName: $packageName,
                version: $version,
                versionNormalized: $normalized,
                releaseDate: $entry['time'] ?? '',
                url: 'https://packagist.org/packages/' . $packageName,
            );
        }

        return null;
    }

    /**
     * Check if a normalized version string meets the minimum stability.
     *
     * Stability hierarchy: dev < alpha < beta < RC < stable
     *
     * @param string $versionNormalized Normalized version (e.g. "3.0.0.0-beta23")
     *
     * @return bool True if the version is at or above minimum stability
     */
    private function meetsStability(string $versionNormalized): bool
    {
        $stabilityLevels = [
            'dev' => 0,
            'alpha' => 1,
            'beta' => 2,
            'RC' => 3,
            'stable' => 4,
        ];

        $requiredLevel = $stabilityLevels[$this->minimumStability] ?? 4;

        // Determine the version's stability from its normalized string
        $versionStability = 'stable';
        $lower = strtolower($versionNormalized);

        if (str_contains($lower, '-alpha')) {
            $versionStability = 'alpha';
        } elseif (str_contains($lower, '-beta')) {
            $versionStability = 'beta';
        } elseif (str_contains($lower, '-rc') || str_contains($lower, '-RC')) {
            $versionStability = 'RC';
        } elseif (str_contains($lower, '-dev') || str_contains($lower, 'dev')) {
            $versionStability = 'dev';
        }

        $versionLevel = $stabilityLevels[$versionStability] ?? 4;

        return $versionLevel >= $requiredLevel;
    }
}
