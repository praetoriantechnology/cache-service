<?php

namespace Praetorian\Prometheus\CacheService;

use Generator;
use InvalidArgumentException;

class RedisCacheService implements CacheServiceInterface
{
    const MIN_TTL = 1;
    const MAX_TTL = 3600;

    /** @var $redis */
    private $redis;
    private $host;
    private $port;

    public function __construct(string $host, ?int $port = null)
    {
        $this->host = $host;
        $this->port = $port;
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
    public function get(string $key)
    {
        $this->reconnect();
        $value = phpiredis_command_bs($this->getRedis(), [
            'GET', $key,
        ]);

        if (!$value) {
            return null;
        }

        return igbinary_unserialize($value);
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

    protected function getRedis()
    {
        return $this->redis;
    }


    /**
     * {@inheritdoc}
     */
    public function enqueue($queue, $value): self
    {
        $this->reconnect();

        phpiredis_command_bs($this->getRedis(), [
            'RPUSH', $queue, igbinary_serialize($value)
        ]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function pop($queue)
    {
        $this->reconnect();

        $item = phpiredis_command_bs($this->getRedis(), [
            'LPOP', $queue
        ]);

        if (!$item) {
            return null;
        }

        return igbinary_unserialize($item);
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

    private function reconnect()
    {
        if ($this->redis === false || $this->redis === null) {
            $this->redis = phpiredis_connect($this->host, $this->port);
        }

        return $this;
    }

    public function tag($key, $tag): self
    {
        $this->reconnect();
        $item = phpiredis_command_bs($this->getRedis(), [
            'SADD', $tag, $key
        ]);

        return $this;
    }

    public function untag($key, $tag): self
    {
        $this->reconnect();
        $item = phpiredis_command_bs($this->getRedis(), [
            'SREM', $tag, $key
        ]);

        return $this;
    }
}
