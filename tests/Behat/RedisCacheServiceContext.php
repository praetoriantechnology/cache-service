<?php

declare(strict_types=1);

namespace Praetorian\Tests\CacheService\Behat;

use Behat\Behat\Context\Context;
use PHPUnit\Framework\Assert;
use Praetorian\CacheService\RedisCacheService;

final class RedisCacheServiceContext implements Context
{
    private RedisCacheService $redisCacheService;

    public function __construct(string $host, ?int $port = null)
    {
        $this->redisCacheService = new RedisCacheService($host, $port);
    }

    /**
     * @Given the redis cache instance does not contain any value under the key :key
     */
    public function theRedisCacheInstanceDoesNotContainAnyValueUnderTheKey(string $key)
    {
        $this->redisCacheService->delete($key);
    }

    /**
     * @Given the redis cache instance contains :value under the key :key
     */
    public function theRedisCacheInstanceContainsUnderTheKey(mixed $value, string $key)
    {
        $this->redisCacheService->set($key, $value);
    }

    /**
     * @Given the redis cache instance contains :value under the key :key which is not tagged by :tag
     */
    public function theRedisCacheInstanceContainsUnderTheKeyWhichIsNotTaggedBy(mixed $value, string $key, string $tag)
    {
        $this->redisCacheService->set($key, $value);
        //to avoid case when previously existing value under this key was tagged by this tag
        $this->redisCacheService->untag($key, $tag);
    }

    /**
     * @Given the redis cache instance contains :value under the key :key which is tagged by :tag
     */
    public function theRedisCacheInstanceContainsUnderTheKeyWhichIsTaggedBy(mixed $value, string $key, string $tag)
    {
        $this->redisCacheService->set($key, $value, $tag);
    }

    /**
     * @Given the redis cache instance contains :value under the key :key which is tagged by :tag1 and :tag2
     */
    public function theRedisCacheInstanceContainsUnderTheKeyWhichIsTaggedByAnd(mixed $value, string $key, string $tag1, string $tag2)
    {
        $this->redisCacheService->set($key, $value, $tag1);
        $this->redisCacheService->tag($key, $tag2);
    }

    /**
     * @Given the redis cache instance is clean
     */
    public function theRedisCacheInstanceIsClean()
    {
        $this->redisCacheService->clear();
    }

    /**
     * @When I add the :value under the :key to the cache
     */
    public function iAddTheUnderTheToTheCache(mixed $value, string $key)
    {
        $this->redisCacheService->set($key, $value);
    }

    /**
     * @Then I should have :value under the :key in the cache
     */
    public function iShouldHaveUnderTheInTheCache(mixed $value, string $key)
    {
        $expectedValue = $value;
        $actualValue = $this->redisCacheService->get($key);
        Assert::assertEquals($expectedValue, $actualValue);
    }

    /**
     * @When I delete value under the :key from the cache
     */
    public function iDeleteValueUnderTheFromTheCache(string $key)
    {
        $this->redisCacheService->delete($key);
    }

    /**
     * @Then I should not have any value under the :key in the cache
     */
    public function iShouldNotHaveAnyValueUnderTheInTheCache(string $key)
    {
        Assert::assertNull($this->redisCacheService->get($key));
    }

    /**
     * @When I add the :value under the :key tagged with :tag to the cache
     */
    public function iAddTheUnderTheTaggedWithToTheCache(mixed $value, string $key, string $tag)
    {
        $this->redisCacheService->set($key, $value, $tag);
    }

    /**
     * @Then I should have :value tagged by the :tag under the :key in the cache
     */
    public function iShouldHaveTaggedByTheUnderTheInTheCache(mixed $value, string $tag, string $key)
    {
        $items = $this->redisCacheService->getTagged($tag);

        foreach ($items as $itemKey => $itemValue) {
            if ($itemKey === $key && $itemValue === $value) {
                return;
            }
        }

        throw new \RuntimeException(sprintf('Cache does not contain value %s tagged by %s', $value, $tag));
    }

    /**
     * @Then I should not have :value tagged by the :tag under the :key in the cache
     */
    public function iShouldNotHaveTaggedByTheUnderTheInTheCache(mixed $value, string $tag, string $key)
    {
        $items = $this->redisCacheService->getTagged($tag);

        foreach ($items as $itemKey => $itemValue) {
            if ($itemKey === $key && $itemValue === $value) {
                throw new \RuntimeException(sprintf('Cache contains value %s tagged by %s', $value, $tag));
            }
        }
    }

    /**
     * @When I tag the :key with :tag
     */
    public function iTagTheWith(string $key, string $tag)
    {
        $this->redisCacheService->tag($key, $tag);
    }

    /**
     * @When I untag the :key with :tag
     */
    public function iUntagTheWith(string $key, string $tag)
    {
        $this->redisCacheService->untag($key, $tag);
    }

    /**
     * @Then I should not have key :key tagged by the :tag in the cache
     */
    public function iShouldNotHaveKeyTaggedByTheInTheCache(string $key, string $tag)
    {
        $items = $this->redisCacheService->getTagged($tag);

        foreach ($items as $itemKey => $itemValue) {
            if ($itemKey === $key) {
                throw new \RuntimeException(sprintf('Cache contains key %s tagged by %s', $key, $tag));
            }
        }
    }

    /**
     * @Then I should not have any key tagged by the :tag in the cache
     */
    public function iShouldNotHaveAnyKeyTaggedByTheInTheCache(string $tag)
    {
        $items = $this->redisCacheService->getTagged($tag);

        foreach ($items as $itemKey => $itemValue) {
            throw new \RuntimeException(sprintf('Cache contains key %s tagged by %s', $key, $tag));
        }
    }

    /**
     * @When I clear by tag :tag from cache
     */
    public function iClearByTagFromCache(string $tag)
    {
        $this->redisCacheService->clearByTag($tag);
    }

    /**
     * @Then I should have exactly :n key(s) tagged by the :tag in the cache
     */
    public function iShouldHaveExactlyKeysTaggedByTheInTheCache(int $n, string $tag)
    {
        $items = $this->redisCacheService->getTagged($tag);

        Assert::assertCount($n, iterator_to_array($items));
    }
}
