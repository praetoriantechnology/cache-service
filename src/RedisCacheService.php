<?php

namespace Praetorian\CacheService;

use Generator;
use InvalidArgumentException;

class RedisCacheService implements CacheServiceInterface
{
    const MIN_TTL = 1;
    const MAX_TTL = 30 * 24 * 3600;

    /** @var $redis */
    private $redis;

    public function __construct(
        private string $host,
        private ?int $port = null)
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

        foreach ($members as $member) {
            $memberValue = $this->get($member);
            if ($memberValue) {
                yield $member => $memberValue;
            }
        }
    }

    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException
     */
    public function set(string $key, $value, $tag = null, $ttl = null): self
    {
        $this->reconnect();
        $operations = $this->buildSetCommand($key, $value, $tag, $ttl);
        phpiredis_multi_command_bs($this->getRedis(), $operations);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, bool $skipDeserialize = false)
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

        return igbinary_unserialize($value);
    }

    public function increase($key, int $value): self
    {
        $this->reconnect();
        $item = phpiredis_command_bs($this->getRedis(), [
            'INCRBY', $key, $value,
        ]);

        return $this;
    }

    public function decrease($key, int $value): self
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
     */
    public function enqueue($queue, $value): self
    {
        $this->reconnect();

        phpiredis_command_bs($this->getRedis(), [
            'RPUSH', $queue, igbinary_serialize($value),
        ]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function pop($queue, int $range = 1)
    {
        $this->reconnect();
        if ($range === 1) {
            $item = phpiredis_command_bs($this->getRedis(), [
                'LPOP', $queue,
            ]);

            if (!$item) {
                return null;
            }

            return igbinary_unserialize($item);
        }

        $items = phpiredis_command_bs($this->getRedis(), [
            'LRANGE', $queue, 0, $range,
        ]);

        if (!$items) {
            return null;
        }

        $itemsParsed = [];
        foreach ($items as $item) {
            $itemsParsed[] = igbinary_unserialize($item);
        }

        return $itemsParsed;
    }

    public function tag($key, $tag): self
    {
        $this->reconnect();
        $item = phpiredis_command_bs($this->getRedis(), [
            'SADD', $tag, $key,
        ]);

        return $this;
    }

    public function untag($key, $tag): self
    {
        $this->reconnect();
        $item = phpiredis_command_bs($this->getRedis(), [
            'SREM', $tag, $key,
        ]);

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
     * @param string $key
     * @param object $value
     * @param null|string $tag
     * @param null|int $ttl
     * @throws InvalidArgumentException
     * @return array
     */
    protected function buildSetCommand(string $key, $value, ?string $tag = null, ?int $ttl = null): array
    {
        $operations = [];
        if ($ttl !== null) {
            if ($ttl < static::MIN_TTL || $ttl > static::MAX_TTL) {
                throw new InvalidArgumentException(sprintf('TTL must be a value between (including) %d and %d. Provided: %d.', static::MIN_TTL, static::MAX_TTL, $ttl));
            }

            $operations[] = ['SETEX', $key, $ttl, igbinary_serialize($value)];
        } else {
            $operations[] = ['SET', $key, igbinary_serialize($value)];
        }

        if ($tag) {
            $operations[] = ['SADD', $tag, $key];
        }

        return $operations;
    }

    protected function reconnect()
    {
        if ($this->getRedis() === false || $this->getRedis() === null) {
            $this->redis = phpiredis_connect($this->host, $this->port);
        }

        return $this;
    }
}
