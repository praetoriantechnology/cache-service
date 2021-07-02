<?php

declare(strict_types=1);

namespace Praetorian\Tests\CacheService;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Praetorian\CacheService\RedisCacheService;
use ReflectionClass;

final class RedisCacheServiceTest extends TestCase
{
    use \phpmock\phpunit\PHPMock;
    const TESTED_CLASS = RedisCacheService::class;

    public function testConstructor(): void
    {
        $phpiredisConnect = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_connect');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['reconnect'])
            ->getMock();

        $phpiredisConnect->expects($this->exactly(2))->withConsecutive([
            'somehost.com',
        ], ['other.com', 6139]);

        $reflectedClass = new ReflectionClass(static::TESTED_CLASS);
        $constructor = $reflectedClass->getConstructor();
        $constructor->invoke($mock, 'somehost.com');
        $constructor->invoke($mock, 'other.com', 6139);
    }

    public function testGetRedis()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');
        $phpiredisConnect = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_connect');

        $phpiredisConnect->expects($this->once())->willReturn('fake_redis');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['get'])
            ->setConstructorArgs(['127.0.0.1', 1111])
            ->getMock();

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', ['GET', 'sample_key']);

        $mock->get('sample_key');
    }

    public function testClear()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->onlyMethods(['reconnect', 'getRedis'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('reconnect')->willReturn(null);
        $mock->method('getRedis')->willReturn('fake_redis');

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', ['FLUSHALL']);

        $this->assertSame($mock, $mock->clear());
    }

    public function testDelete()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['delete'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', ['DEL', 'sample_key']);

        $this->assertSame($mock, $mock->delete('sample_key'));
    }

    public function testSet_invalidTtl_toLow()
    {
        $this->expectException(InvalidArgumentException::class);

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['set', 'buildSetCommand'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $ttl = RedisCacheService::MIN_TTL - 1;
        $mock->set('sample_key', 'any value', null, $ttl);
    }

    public function testSet_invalidTtl_toHigh()
    {
        $this->expectException(InvalidArgumentException::class);

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['set', 'buildSetCommand'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $ttl = RedisCacheService::MAX_TTL + 1;
        $mock->set('sample_key', 'any value', null, $ttl);
    }

    public function testSet_invalidValue_null()
    {
        $this->expectException(InvalidArgumentException::class);

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['set'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->set('sample_key', null);
    }

    public function testSet_single()
    {
        $phpiredisMultiCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_multi_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['set'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleValue = ['a' => 5, 'b' => 'a', 'c' => ['x']];
        $sampleValueSerialized = igbinary_serialize($sampleValue);

        $phpiredisMultiCommandBs->expects($this->once())->with('fake_redis', [
            ['SET', 'sample_key', $sampleValueSerialized],
        ]);

        $this->assertSame($mock, $mock->set('sample_key', $sampleValue));
    }

    public function testSet_tag()
    {
        $phpiredisMultiCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_multi_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['set'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleValue = ['a' => 5, 'b' => 'a', 'c' => ['x']];
        $sampleValueSerialized = igbinary_serialize($sampleValue);
        $tag = 'test_tag';

        $phpiredisMultiCommandBs->expects($this->once())->with('fake_redis', [
            ['SET', 'sample_key', $sampleValueSerialized],
            ['SADD', 'test_tag', 'sample_key'],
        ]);

        $this->assertSame($mock, $mock->set('sample_key', $sampleValue, $tag));
    }

    public function testSet_ttl()
    {
        $phpiredisMultiCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_multi_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['set'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleValue = ['a' => 5, 'b' => 'a', 'c' => ['x']];
        $sampleValueSerialized = igbinary_serialize($sampleValue);
        $ttl = 360;

        $phpiredisMultiCommandBs->expects($this->once())->with('fake_redis', [
            ['SETEX', 'sample_key', $ttl, $sampleValueSerialized],
        ]);

        $this->assertSame($mock, $mock->set('sample_key', $sampleValue, null, $ttl));
    }

    public function testSet_ttlAndTag()
    {
        $phpiredisMultiCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_multi_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['set'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleValue = ['a' => 5, 'b' => 'a', 'c' => ['x']];
        $sampleValueSerialized = igbinary_serialize($sampleValue);
        $tag = 'test_tag';
        $ttl = 360;

        $phpiredisMultiCommandBs->expects($this->once())->with('fake_redis', [
            ['SETEX', 'sample_key', $ttl, $sampleValueSerialized],
            ['SADD', 'test_tag', 'sample_key'],
        ]);

        $this->assertSame($mock, $mock->set('sample_key', $sampleValue, $tag, $ttl));
    }

    public function testGetTagged_empty()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['getTagged'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', ['SMEMBERS', 'sample_tag'])->willReturn(null);

        $this->assertSame([], iterator_to_array($mock->getTagged('sample_tag')));
    }

    public function testGetTagged()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['getTagged', 'get'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $phpiredisCommandBs->expects($this->exactly(3))->withConsecutive(
            ['fake_redis', ['SMEMBERS', 'sample_tag']],
            ['fake_redis', ['GET', 'sample_key1']],
            ['fake_redis', ['GET', 'sample_key2']]
        )->willReturnOnConsecutiveCalls(
            ['sample_key1', 'sample_key2'],
            igbinary_serialize('testdata1'),
            igbinary_serialize('testdata2'),
        );

        $response = iterator_to_array($mock->getTagged('sample_tag'));
        $this->assertEquals(['sample_key1' => 'testdata1', 'sample_key2' => 'testdata2'], $response);
    }

    public function testGet()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['get'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $phpiredisCommandBs->expects($this->exactly(2))->withConsecutive(
            ['fake_redis', ['GET', 'sample_key1']],
            ['fake_redis', ['GET', 'sample_key2']]
        )->willReturnOnConsecutiveCalls(
            null,
            igbinary_serialize('testdata1')
        );

        $this->assertNull($mock->get('sample_key1'));
        $this->assertEquals('testdata1', $mock->get('sample_key2'));
    }

    public function testGet_skipDeserialize()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['get'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $phpiredisCommandBs->expects($this->exactly(2))->withConsecutive(
            ['fake_redis', ['GET', 'sample_key1']],
            ['fake_redis', ['GET', 'sample_key2']]
        )->willReturnOnConsecutiveCalls(
            null,
            igbinary_serialize('testdata1')
        );

        $this->assertNull($mock->get('sample_key1', true));
        $this->assertEquals(igbinary_serialize('testdata1'), $mock->get('sample_key2', true));
    }

    public function testIncrease()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['increase'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleKey = 'sample_key';
        $sampleValue = 2;

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', [
            'INCRBY', $sampleKey, $sampleValue,
        ]);

        $this->assertSame($mock, $mock->increase($sampleKey, $sampleValue));
    }

    public function testDecrease()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['decrease'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleKey = 'sample_key';
        $sampleValue = 1;

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', [
            'INCRBY', $sampleKey, -1 * $sampleValue,
        ]);

        $this->assertSame($mock, $mock->decrease($sampleKey, $sampleValue));
    }

    public function testEnqueueNonNullItem()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['enqueue'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleQueue = 'sample_queue';
        $sampleValue = 3;
        $sampleValueSerialized = igbinary_serialize($sampleValue);

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', [
            'RPUSH', $sampleQueue, $sampleValueSerialized,
        ]);

        $this->assertSame($mock, $mock->enqueue($sampleQueue, $sampleValue));
    }

    public function testEnqueueNullItem()
    {
        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['enqueue'])
            ->disableOriginalConstructor()
            ->getMock();

        $sampleQueue = 'sample_queue';
        $sampleValue = null;

        $this->expectException(InvalidArgumentException::class);

        $mock->enqueue($sampleQueue, $sampleValue);
    }

    public function testPopOneItem()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['pop'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $phpiredisCommandBs->expects($this->exactly(2))->withConsecutive(
            ['fake_redis', ['LPOP', 'test_empty_queue']],
            ['fake_redis', ['LPOP', 'test_nonempty_queue']]
        )->willReturnOnConsecutiveCalls(
            null,
            igbinary_serialize('testdata')
        );

        $this->assertNull($mock->pop('test_empty_queue'));
        $this->assertEquals('testdata', $mock->pop('test_nonempty_queue'));
    }

    public function testPopRange()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['pop'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $values = [3, 7, 2, 1];
        $serializedValues = array_map(function (int $value) {
            return igbinary_serialize($value);
        }, $values);
        $numberOfItems = count($values);

        $phpiredisCommandBs->expects($this->exactly(2))->withConsecutive(
            ['fake_redis', ['LRANGE', 'test_empty_queue', 0, $numberOfItems]],
            ['fake_redis', ['LRANGE', 'test_nonempty_queue', 0, $numberOfItems]]
        )->willReturnOnConsecutiveCalls(
            [],
            $serializedValues
        );

        $this->assertEquals([], $mock->pop('test_empty_queue', $numberOfItems));
        $this->assertEquals($values, $mock->pop('test_nonempty_queue', $numberOfItems));
    }

    public function testTag()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['tag'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleKey = 'sample_key';
        $sampleTag = 'sample_tag';

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', [
            'SADD', $sampleTag, $sampleKey,
        ]);

        $this->assertSame($mock, $mock->tag($sampleKey, $sampleTag));
    }

    public function testUntag()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['untag'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleKey = 'sample_key';
        $sampleTag = 'sample_tag';

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', [
            'SREM', $sampleTag, $sampleKey,
        ]);

        $this->assertSame($mock, $mock->untag($sampleKey, $sampleTag));
    }

    public function testClearByTag()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(static::TESTED_CLASS)
            ->setMethodsExcept(['clearByTag', 'delete'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleKey = 'sample_key';
        $sampleTag = 'sample_tag';

        $phpiredisCommandBs->expects($this->exactly(2))->withConsecutive(
            ['fake_redis', ['SMEMBERS', $sampleTag]],
            ['fake_redis', ['DEL', $sampleKey]]
        )->willReturnOnConsecutiveCalls(
            [$sampleKey],
            null,
        );

        $this->assertSame($mock, $mock->clearByTag($sampleTag));
    }
}
