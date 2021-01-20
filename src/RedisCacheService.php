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

    public function __construct(string $host, ?int $port = null)
    {
        $this->redis = phpiredis_pconnect($host, $port);
    }

    /**
     * {@inheritdoc}
     */
    public function getTagged(string $tag): Generator
    {
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
        $operations = $this->buildSetCommand($key, $value, $tag, $ttl);
        phpiredis_multi_command_bs($this->getRedis(), $operations);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key)
    {
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
}
