<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Core\Service;

use Horde_Mongo_Client;
use MongoCollection;
use MongoException;
use RuntimeException;
use MongoBinData;

/**
 * MongoDB-based preferences storage service
 *
 * Stores preferences as documents in MongoDB collection.
 * Document format: {uid, scope, key, value}
 *
 * Requires: mongodb PHP extension, Horde_Mongo_Client
 *
 * @category Horde
 * @package  Core
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class MongoPrefsService implements PrefsService
{
    /**
     * MongoDB collection handle
     *
     * @var MongoCollection
     */
    private MongoCollection $collection;

    /**
     * Constructor
     *
     * @param Horde_Mongo_Client $mongoClient MongoDB client
     * @param string $collectionName Collection name (default: horde_prefs)
     * @throws RuntimeException If MongoDB connection fails
     */
    public function __construct(
        private Horde_Mongo_Client $mongoClient,
        private string $collectionName = 'horde_prefs'
    ) {
        try {
            // Select collection (triggers connection)
            $this->collection = $this->mongoClient->selectCollection(
                null, // Use default database from client config
                $this->collectionName
            );

            // Ensure indexes for performance
            $this->ensureIndexes();
        } catch (MongoException $e) {
            throw new RuntimeException(
                'Failed to connect to MongoDB: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Create indexes for efficient querying
     */
    private function ensureIndexes(): void
    {
        try {
            // Compound index for lookups: (uid, scope, key)
            $this->collection->ensureIndex(
                ['uid' => 1, 'scope' => 1, 'key' => 1],
                ['unique' => true, 'background' => true]
            );

            // Index for scope queries
            $this->collection->ensureIndex(
                ['uid' => 1, 'scope' => 1],
                ['background' => true]
            );
        } catch (MongoException $e) {
            // Index creation failure is non-fatal, but log it
            // In production, this would use Horde_Log
            error_log('Failed to create MongoDB indexes: ' . $e->getMessage());
        }
    }

    /**
     * Get preference value
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @return mixed|null Value or null if not found
     * @throws RuntimeException On MongoDB error
     */
    public function getValue(string $uid, string $scope, string $key)
    {
        try {
            $doc = $this->collection->findOne([
                'uid' => $uid,
                'scope' => $scope,
                'key' => $key,
            ]);

            if ($doc === null) {
                return null;
            }

            // Handle MongoBinData for binary values (legacy compatibility)
            $value = $doc['value'];
            if ($value instanceof MongoBinData) {
                return $value->bin;
            }

            return $value;
        } catch (MongoException $e) {
            throw new RuntimeException(
                'MongoDB query failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Set preference value
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @param mixed $value Preference value
     * @throws RuntimeException On MongoDB error
     */
    public function setValue(string $uid, string $scope, string $key, $value): void
    {
        try {
            $query = [
                'uid' => $uid,
                'scope' => $scope,
                'key' => $key,
            ];

            $document = array_merge($query, [
                'value' => $value,
            ]);

            // Upsert: insert if not exists, update if exists
            $this->collection->update(
                $query,
                $document,
                ['upsert' => true]
            );
        } catch (MongoException $e) {
            throw new RuntimeException(
                'MongoDB update failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Delete preference
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @throws RuntimeException On MongoDB error
     */
    public function deleteValue(string $uid, string $scope, string $key): void
    {
        try {
            $this->collection->remove([
                'uid' => $uid,
                'scope' => $scope,
                'key' => $key,
            ]);
        } catch (MongoException $e) {
            throw new RuntimeException(
                'MongoDB delete failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Get all preferences for user in scope
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @return array Associative array of key => value
     * @throws RuntimeException On MongoDB error
     */
    public function getAllInScope(string $uid, string $scope): array
    {
        try {
            $cursor = $this->collection->find([
                'uid' => $uid,
                'scope' => $scope,
            ]);

            $result = [];
            foreach ($cursor as $doc) {
                $key = $doc['key'];
                $value = $doc['value'];

                // Handle MongoBinData
                if ($value instanceof MongoBinData) {
                    $value = $value->bin;
                }

                $result[$key] = $value;
            }

            return $result;
        } catch (MongoException $e) {
            throw new RuntimeException(
                'MongoDB query failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Check if preference exists
     *
     * @param string $uid User ID
     * @param string $scope App name
     * @param string $key Preference key
     * @return bool
     * @throws RuntimeException On MongoDB error
     */
    public function exists(string $uid, string $scope, string $key): bool
    {
        try {
            $count = $this->collection->count([
                'uid' => $uid,
                'scope' => $scope,
                'key' => $key,
            ]);

            return $count > 0;
        } catch (MongoException $e) {
            throw new RuntimeException(
                'MongoDB query failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
