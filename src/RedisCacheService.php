<?php

declare(strict_types=1);

namespace Praetorian\CacheService;

use Generator;
use InvalidArgumentException;
use phpDocumentor\Reflection\DocBlock\Tags\Generic;

class RedisCacheService implements CacheServiceInterface
{
    const DEFAULT_REDIS_PORT = 6139;
    const MIN_TTL = 1;
    const MAX_TTL = 30 * 24 * 3600;

    private const TAGS_SET_NAME_PREFIX = 'TAGS:';

    /** @var */
    private $redis;

    public function __construct(
        private string $host,
        private ?int $port = self::DEFAULT_REDIS_PORT)
    {
        $this->reconnect();
    }

    /**
     * {@inheritdoc}
     */
    public function getTagged(string $tag): Generator
    {
        $this->reconnect();
        $members = phpiredis_command_bs($this->getRedis(), ['SMEMBERS', $tag]);
        if (empty($members)) {
            yield from [];

            return;
        }

        $anyResults = false;

        foreach ($members as $member) {
            $memberValue = $this->get($member);
            if ($memberValue) {
                $anyResults = true;
                yield $member => $memberValue;
            } else {
                $this->delete($member); // fix for expired (TTL) elements which are still in the
            }
        }

        if (!$anyResults) {
            yield from [];

            return;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function set(string $key, mixed $value, ?string $tag = null, ?int $ttl = null, ?int $score = null): self
    {
        if (null === $value) {
            throw new InvalidArgumentException('Can\'t set null item');
        }

        $operations = $this->buildSetCommand($key, $value, $tag, $ttl, $score);

        //$this->untagKeyFromAllTags($key); Removed 26.07: this causes items to lose other tags

        $this->reconnect();
        phpiredis_multi_command_bs($this->getRedis(), $operations);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, bool $skipDeserialize = false): mixed
    {
        $this->reconnect();
        $value = phpiredis_command_bs($this->getRedis(), [
            'GET', $key,
        ]);

        if (!$value) {
            return null;
        }

        if ($skipDeserialize) {
            return $value;
        }

        return \igbinary_unserialize($value);
    }

    public function increase(string $key, int $value): self
    {
        $this->reconnect();
        $item = phpiredis_command_bs($this->getRedis(), [
            'INCRBY', $key, $value,
        ]);

        return $this;
    }

    public function decrease(string $key, int $value): self
    {
        $this->reconnect();
        $item = phpiredis_command_bs($this->getRedis(), [
            'INCRBY', $key, -1 * $value,
        ]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): self
    {
        $this->untagKeyFromAllTags($key);

        $this->reconnect();
        phpiredis_command_bs($this->getRedis(), [
            'DEL', $key,
        ]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): self
    {
        $this->reconnect();
        phpiredis_command_bs($this->getRedis(), [
            'FLUSHALL',
        ]);

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function enqueue(string $queue, mixed $value): self
    {
        if (null === $value) {
            throw new InvalidArgumentException('Can\'t enqueue null item');
        }

        $this->reconnect();

        phpiredis_command_bs($this->getRedis(), [
            'RPUSH', $queue, \igbinary_serialize($value),
        ]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function pop(string $queue, int $range = 1): mixed
    {
        $this->reconnect();
        if (1 === $range) {
            $item = phpiredis_command_bs($this->getRedis(), [
                'LPOP', $queue,
            ]);

            if (!$item) {
                return null;
            }

            return \igbinary_unserialize($item);
        }

        $items = phpiredis_command_bs($this->getRedis(), [
            'LRANGE', $queue, 0, $range,
        ]);

        $itemsParsed = [];
        foreach ($items as $item) {
            $itemsParsed[] = \igbinary_unserialize($item);
        }

        return $itemsParsed;
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function tag(string $key, string $tag, ?int $score = null): self
    {
        if (null === $this->get($key)) {
            throw new InvalidArgumentException(\sprintf('Can\'t tag non-existing key "%s"', $key));
        }

        $this->reconnect();

        $operations = [];

        if ($score !== null) {
            $operations[] = [
                'ZADD', $tag, $score, $key
            ]; 
        } else {
            $operations[] = [
                'SADD', $tag, $key
            ];    
        }


        $operations[] = ['SADD', self::TAGS_SET_NAME_PREFIX.$key, $tag];

        phpiredis_multi_command_bs($this->getRedis(), $operations);

        return $this;
    }

    public function untag(string $key, string $tag, bool $wasScored = false): self
    {
        $this->reconnect();

        $operations = [
            [ $wasScored ? 'ZREM' : 'SREM', $tag, $key],
            ['SREM', self::TAGS_SET_NAME_PREFIX.$key, $tag],
        ];

        phpiredis_multi_command_bs($this->getRedis(), $operations);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function clearByTag(string $tag): CacheServiceInterface
    {
        $this->reconnect();
        $members = phpiredis_command_bs($this->getRedis(), ['SMEMBERS', $tag]);

        foreach ($members as $member) {
            $this->delete($member);
        }

        return $this;
    }

    protected function getRedis()
    {
        return $this->redis;
    }

    /**
     * Prepares a single set command.
     *
     * @throws InvalidArgumentException
     */
    protected function buildSetCommand(string $key, mixed $value, ?string $tag = null, ?int $ttl = null, ?int $score =  null): array
    {
        $operations = [];
        if (null !== $ttl) {
            if ($ttl < static::MIN_TTL || $ttl > static::MAX_TTL) {
                throw new InvalidArgumentException(\sprintf('TTL must be a value between (including) %d and %d. Provided: %d.', static::MIN_TTL, static::MAX_TTL, $ttl));
            }

            $operations[] = ['SETEX', $key, $ttl, \igbinary_serialize($value)];
        } else {
            $operations[] = ['SET', $key, \igbinary_serialize($value)];
        }

        if ($tag) {
            if ($score !== null) {
                $operations[] = ['ZADD', $tag, $score, $key];
            } else {
                $operations[] = ['SADD', $tag, $key];
            }
            
            $operations[] = ['SADD', self::TAGS_SET_NAME_PREFIX.$key, $tag];
        }

        return $operations;
    }

    protected function reconnect()
    {
        if (false === $this->getRedis() || null === $this->getRedis()) {
            $this->redis = phpiredis_connect($this->host, $this->port);
        }

        return $this;
    }

    public function getCardinality(string $set, bool $sortedSet = false): int 
    {
        $this->reconnect();

        return phpiredis_command_bs($this->getRedis(), [$sortedSet ? 'ZCARD' : 'SCARD', $set]);
    }

    public function getSorted(string $set, int $count, int $offset = 0): Generator 
    {
        $this->reconnect();
        $members = phpiredis_command_bs($this->getRedis(), ['ZREVRANGE', $set, $offset, $count]);
        if (empty($members)) {
            yield from [];

            return;
        }

        $anyResults = false;

        foreach ($members as $member) {
            $memberValue = $this->get($member);
            if ($memberValue) {
                $anyResults = true;
                yield $member => $memberValue;
            } else {
                $this->delete($member); // fix for expired (TTL) elements which are still in the
            }
        }

        if (!$anyResults) {
            yield from [];

            return;
        }
    }

    private function untagKeyFromAllTags(string $key): void
    {
        $this->reconnect();

        $tags = phpiredis_command_bs($this->getRedis(), ['SMEMBERS', self::TAGS_SET_NAME_PREFIX.$key]);

        foreach ($tags as $tag) {
            $this->untag($key, $tag);
        }
    }
}
