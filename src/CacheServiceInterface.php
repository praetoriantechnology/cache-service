<?php

namespace Praetorian\CacheService;

use Generator;

interface CacheServiceInterface
{
    /**
     * Gets the unserialized object set using the provided key or null if such
     * object does not exist.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key, bool $skipDeserialize = false): mixed;

    /**
     * Sets the given object under the given key.
     *
     * Setting of tag allows to group objects.
     *
     * TTL sets time-to-live in seconds: 0 or null disables TTL.
     *
     * @param string $key
     * @param mixed $value
     * @param null|string $tag
     * @param null|int $ttl
     * @return CacheServiceInterface
     */
    public function set(string $key, mixed $value, ?string $tag = null, ?int $ttl = null): CacheServiceInterface;

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
     * Deletes all entries under given tag.
     *
     * @param string $tag
     * @return CacheServiceInterface
     */
    public function clearByTag(string $tag): CacheServiceInterface;

    /**
     * Gets an array of unserialized objects from under a given tag ordered by
     * their
     */
    public function getTagged(string $tag): Generator;

    /**
     * Puts entry on top of a queue.
     */
    public function enqueue(string $queue, mixed $value): CacheServiceInterface;

    /**
     * Pops out first element from the queue.
     */
    public function pop(string $queue, int $range = 1): mixed;

    public function tag(string $key, string $tag): CacheServiceInterface;

    public function untag(string $key, string $tag): CacheServiceInterface;

    public function increase(string $key, int $value): CacheServiceInterface;

    public function decrease(string $key, int $value): CacheServiceInterface;
}
