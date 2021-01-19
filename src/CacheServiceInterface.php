<?php

namespace Praetorian\Prometheus\CacheService;

use Generator;

interface CacheServiceInterface
{
    /**
     * Gets the unserialized object set using the provided key or null if such
     * object does not exist.
     *
     * @param string $key
     * @return mixed|null
     */
    public function get(string $key);

    /**
     * Sets the given object under the given key.
     *
     * Setting of tag allows to group objects.
     *
     * TTL sets time-to-live in seconds: 0 or null disables TTL.
     *
     * @param string $key
     * @param $value
     * @param null|string $tag
     * @param null|int $ttl
     * @return CacheServiceInterface
     */
    public function set(string $key, $value, ?string $tag = null, ?int $ttl = null): CacheServiceInterface;

    /**
     * Deletes entry under given key.
     *
     * @param string $key
     * @return CacheServiceInterface
     */
    public function delete(string $key): ?CacheServiceInterface;

    /**
     * Deletes all entries.
     *
     * @return CacheServiceInterface
     */
    public function clear(): ?CacheServiceInterface;

    /**
     * Gets an array of unserialized objects from under a given tag ordered by
     * their
     */
    public function getTagged(string $tag): Generator;
}
