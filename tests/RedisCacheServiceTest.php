<?php

declare(strict_types=1);

namespace Praetorian\Tests\CacheService;

use InvalidArgumentException;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Praetorian\CacheService\RedisCacheService;
use ReflectionClass;

use function array_map;
use function count;
use function igbinary_serialize;
use function iterator_to_array;

final class RedisCacheServiceTest extends TestCase
{
    use PHPMock;

    const TESTED_CLASS = RedisCacheService::class;

    public function testConstructor(): void
    {
        $phpiredisConnect = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_connect');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->disableOriginalConstructor()
            ->setMethodsExcept(['reconnect'])
            ->getMock();

        $phpiredisConnect->expects($this->exactly(2))->withConsecutive([
            'somehost.com',
        ], ['other.com', 6139]);

        $reflectedClass = new ReflectionClass(self::TESTED_CLASS);
        $constructor = $reflectedClass->getConstructor();
        $constructor->invoke($mock, 'somehost.com');
        $constructor->invoke($mock, 'other.com', 6139);
    }

    public function testGetRedis()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');
        $phpiredisConnect = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_connect');

        $phpiredisConnect->expects($this->once())->willReturn('fake_redis');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['get'])
            ->setConstructorArgs(['127.0.0.1', 1111])
            ->getMock();

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', ['GET', 'sample_key']);

        $mock->get('sample_key');
    }

    public function testClear()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->onlyMethods(['reconnect', 'getRedis'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('reconnect')->willReturn(null);
        $mock->method('getRedis')->willReturn('fake_redis');

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', ['FLUSHALL']);

        $this->assertSame($mock, $mock->clear());
    }

    public function testDeleteUntaggedKey()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['delete'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleKey = 'sample_key';

        $phpiredisCommandBs->expects($this->exactly(2))->withConsecutive(
            ['fake_redis', ['SMEMBERS', 'TAGS:'.$sampleKey]],
            ['fake_redis', ['DEL', $sampleKey]]
        )->willReturnOnConsecutiveCalls(
            [],
            null,
        );

        $this->assertSame($mock, $mock->delete($sampleKey));
    }

    public function testDeleteTaggedKey()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['delete'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleKey = 'sample_key';
        $sampleTag = 'sample_tag';

        $phpiredisCommandBs->expects($this->exactly(2))->withConsecutive(
            ['fake_redis', ['SMEMBERS', 'TAGS:'.$sampleKey]],
            ['fake_redis', ['DEL', $sampleKey]]
        )->willReturnOnConsecutiveCalls(
            [$sampleTag],
            null,
        );

        $mock->expects($this->once())
            ->method('untag')
            ->with($sampleKey, $sampleTag);

        $this->assertSame($mock, $mock->delete($sampleKey));
    }

    public function testSetInvalidTtlToLow()
    {
        $this->expectException(InvalidArgumentException::class);

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['set', 'buildSetCommand'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $ttl = RedisCacheService::MIN_TTL - 1;
        $mock->set('sample_key', 'any value', null, $ttl);
    }

    public function testSetInvalidTtlToHigh()
    {
        $this->expectException(InvalidArgumentException::class);

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['set', 'buildSetCommand'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $ttl = RedisCacheService::MAX_TTL + 1;
        $mock->set('sample_key', 'any value', null, $ttl);
    }

    public function testSetInvalidValueNull()
    {
        $this->expectException(InvalidArgumentException::class);

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['set'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->set('sample_key', null);
    }

    public function testSetSingle()
    {
        $phpiredisMultiCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_multi_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
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

        // $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');
        // $phpiredisCommandBs->expects($this->once())->with('fake_redis', ['SMEMBERS', 'TAGS:sample_key'])->willReturn([]);

        $this->assertSame($mock, $mock->set('sample_key', $sampleValue));
    }

    public function testSetTag()
    {
        $phpiredisMultiCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_multi_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
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
            ['SADD', 'TAGS:sample_key', 'test_tag'],
        ]);

        // $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');
        // $phpiredisCommandBs->expects($this->once())->with('fake_redis', ['SMEMBERS', 'TAGS:sample_key'])->willReturn([]);

        $this->assertSame($mock, $mock->set('sample_key', $sampleValue, $tag));
    }

    public function testSetTtl()
    {
        $phpiredisMultiCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_multi_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
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

        // $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');
        // $phpiredisCommandBs->expects($this->once())->with('fake_redis', ['SMEMBERS', 'TAGS:sample_key'])->willReturn([]);

        $this->assertSame($mock, $mock->set('sample_key', $sampleValue, null, $ttl));
    }

    public function testSetTtlAndTag()
    {
        $phpiredisMultiCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_multi_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
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
            ['SADD', 'TAGS:sample_key', 'test_tag'],
        ]);

        // $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');
        // $phpiredisCommandBs->expects($this->once())->with('fake_redis', ['SMEMBERS', 'TAGS:sample_key'])->willReturn([]);

        $this->assertSame($mock, $mock->set('sample_key', $sampleValue, $tag, $ttl));
    }

    public function testGetTaggedEmpty()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
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

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['getTagged', 'get'])
            ->onlyMethods(['getRedis', 'reconnect']) //TODO: fix duplicate usage
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');
        $mock->expects($this->exactly(0))
            ->method('delete')
            ->will($this->returnValue($mock));

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

    public function testGetTaggedExpired()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['getTagged', 'get'])
            ->onlyMethods(['getRedis', 'reconnect']) //TODO: fix duplicate usage
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $mock->expects($this->once())
            ->method('delete')
            ->will($this->returnValue($mock));

        $phpiredisCommandBs->expects($this->exactly(3))->withConsecutive(
            ['fake_redis', ['SMEMBERS', 'sample_tag']],
            ['fake_redis', ['GET', 'sample_key1']],
            ['fake_redis', ['GET', 'sample_expired']]
        )->willReturnOnConsecutiveCalls(
            ['sample_key1', 'sample_expired'],
            igbinary_serialize('testdata1'),
            null,
        );

        $response = iterator_to_array($mock->getTagged('sample_tag'));
        $this->assertEquals(['sample_key1' => 'testdata1'], $response);
    }

    public function testGetTaggedAllExpired()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['getTagged', 'get'])
            ->onlyMethods(['getRedis', 'reconnect']) //TODO: fix duplicate usage
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $mock->expects($this->exactly(2))
            ->method('delete')
            ->will($this->returnValue($mock));

        $phpiredisCommandBs->expects($this->exactly(3))->withConsecutive(
            ['fake_redis', ['SMEMBERS', 'sample_tag']],
            ['fake_redis', ['GET', 'sample_expired2']],
            ['fake_redis', ['GET', 'sample_expired']]
        )->willReturnOnConsecutiveCalls(
            ['sample_expired2', 'sample_expired'],
            null,
            null,
        );

        $response = iterator_to_array($mock->getTagged('sample_tag'));
        $this->assertEquals([], $response);
    }

    public function testGet()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
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

    public function testGetSkipDeserialize()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
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

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['increase'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleKey = 'sample_key';
        $sampleValue = 2;

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', [
            'INCRBY',
            $sampleKey,
            $sampleValue,
        ]);

        $this->assertSame($mock, $mock->increase($sampleKey, $sampleValue));
    }

    public function testDecrease()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['decrease'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleKey = 'sample_key';
        $sampleValue = 1;

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', [
            'INCRBY',
            $sampleKey,
            -1 * $sampleValue,
        ]);

        $this->assertSame($mock, $mock->decrease($sampleKey, $sampleValue));
    }

    public function testEnqueueNonNullItem()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['enqueue'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleQueue = 'sample_queue';
        $sampleValue = 3;
        $sampleValueSerialized = igbinary_serialize($sampleValue);

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', [
            'RPUSH',
            $sampleQueue,
            $sampleValueSerialized,
        ]);

        $this->assertSame($mock, $mock->enqueue($sampleQueue, $sampleValue));
    }

    public function testEnqueueNullItem()
    {
        $mock = $this->getMockBuilder(self::TESTED_CLASS)
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

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
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

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['pop'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $values = [3, 7, 2, 1];
        $serializedValues = array_map(fn(int $value) => igbinary_serialize($value), $values);
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
        $phpiredisMultiCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_multi_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['tag'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleKey = 'sample_key';
        $sampleTag = 'sample_tag';

        $phpiredisMultiCommandBs->expects($this->once())->with('fake_redis', [
            ['SADD', $sampleTag, $sampleKey],
            ['SADD', 'TAGS:'.$sampleKey, $sampleTag],
        ]);

        $mock->expects($this->once())
            ->method('get')
            ->with($sampleKey)
            ->willReturn('sample_value');

        $this->assertSame($mock, $mock->tag($sampleKey, $sampleTag));
    }

    public function testTagInvalidNonExistingKey()
    {
        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['tag'])
            ->disableOriginalConstructor()
            ->getMock();

        $sampleKey = 'sample_key';
        $sampleTag = 'sample_tag';

        $mock->expects($this->once())
            ->method('get')
            ->with($sampleKey)
            ->willReturn(null);

        $this->expectException(InvalidArgumentException::class);

        $mock->tag($sampleKey, $sampleTag);
    }

    public function testUntag()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $phpiredisMultiCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_multi_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['untag'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleKey = 'sample_key';
        $sampleTag = 'sample_tag';

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', [
            'TYPE',
            $sampleTag
        ])->willReturn('string');

        $phpiredisMultiCommandBs->expects($this->once())->with('fake_redis', [
            ['SREM', $sampleTag, $sampleKey],
            ['SREM', 'TAGS:'.$sampleKey, $sampleTag],
        ]);

        $this->assertSame($mock, $mock->untag($sampleKey, $sampleTag));
    }

    public function testClearByTag()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['clearByTag'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $sampleKey = 'sample_key';
        $sampleTag = 'sample_tag';

        $phpiredisCommandBs->expects($this->once())->with('fake_redis', [
            'SMEMBERS',
            $sampleTag,
        ])->willReturn(
            [$sampleKey]
        );

        $mock->expects($this->once())
            ->method('delete')
            ->with($sampleKey);

        $this->assertSame($mock, $mock->clearByTag($sampleTag));
    }

    public function testGetQueueLength()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['getQueueLength'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $phpiredisCommandBs->expects($this->once())
            ->with('fake_redis', ['LLEN', 'sample_queue_1'])
            ->willReturn(10);

        $this->assertEquals(10, $mock->getQueueLength('sample_queue_1'));
    }

    public function testGetCardinality()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['getCardinality'])
            ->onlyMethods(['getRedis', 'reconnect'])
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');

        $phpiredisCommandBs->expects($this->exactly(2))->withConsecutive(
            ['fake_redis', ['ZCARD', 'sample_set_1']],
            ['fake_redis', ['SCARD', 'sample_set_1']],
        )->willReturnOnConsecutiveCalls(
            10,
            10,
        );

        $this->assertEquals(10, $mock->getCardinality('sample_set_1', true));
        $this->assertEquals(10, $mock->getCardinality('sample_set_1'));
    }

//    public function testGetQueue()
//    {
//        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');
//
//        $mock = $this->getMockBuilder(self::TESTED_CLASS)
//            ->setMethodsExcept(['getQueue'])
//            ->onlyMethods(['getRedis', 'reconnect'])
//            ->disableOriginalConstructor()
//            ->getMock();
//
//        $mock->method('getRedis')->willReturn('fake_redis');
//
//        $phpiredisCommandBs->expects($this->once())
//            ->with('fake_redis', ['LLEN', 'sample_queue_1'])
//            ->willReturn(3);
//
//        $phpiredisCommandBs->expects($this->exactly(3))
//            ->withConsecutive(
//                ['fake_redis', ['RPOPLPUSH', 'sample_queue_1']],
//                ['fake_redis', ['RPOPLPUSH', 'sample_queue_1']],
//                ['fake_redis', ['RPOPLPUSH', 'sample_queue_1']],
//            )->willReturnOnConsecutiveCalls(['test'], ['test2'], ['test3']);
//
//        var_dump($mock->getQueue('sample_queue_1'));
//
//        $this->assertEquals(['value1', 'value2', 'value3'], $mock->getQueue('sample_queue_1'));
//        $this->assertEquals(['value2', 'value3'], $mock->getQueue('sample_queue_1'));
//        $this->assertEquals(['value3'], $mock->getQueue('sample_queue_1'));
//    }

    public function testGetSorted()
    {
        $phpiredisCommandBs = $this->getFunctionMock('Praetorian\CacheService', 'phpiredis_command_bs');

        $mock = $this->getMockBuilder(self::TESTED_CLASS)
            ->setMethodsExcept(['getSorted', 'get'])
            ->onlyMethods(['getRedis', 'reconnect']) //TODO: fix duplicate usage
            ->disableOriginalConstructor()
            ->getMock();

        $mock->method('getRedis')->willReturn('fake_redis');
        $mock->expects($this->exactly(0))
            ->method('delete')
            ->will($this->returnValue($mock));

        $phpiredisCommandBs->expects($this->exactly(6))->withConsecutive(
            ['fake_redis', ['ZRANGE', 'sample_set1', 0, 5]],
            ['fake_redis', ['GET', 'sample_key1']],
            ['fake_redis', ['GET', 'sample_key2']],
            ['fake_redis', ['GET', 'sample_key3']],
            ['fake_redis', ['GET', 'sample_key4']],
            ['fake_redis', ['GET', 'sample_key5']],
        )->willReturnOnConsecutiveCalls(
            ['sample_key1', 'sample_key2', 'sample_key3', 'sample_key4', 'sample_key5'],
            igbinary_serialize('testdata1'),
            igbinary_serialize('testdata2'),
            igbinary_serialize('testdata3'),
            igbinary_serialize('testdata4'),
            igbinary_serialize('testdata5'),
        );

        $this->assertEquals(
            [
                'sample_key1' => 'testdata1',
                'sample_key2' => 'testdata2',
                'sample_key3' => 'testdata3',
                'sample_key4' => 'testdata4',
                'sample_key5' => 'testdata5'
            ],
            iterator_to_array($mock->getSorted('sample_set1', 5, 0))
        );
    }
}
