<?php

namespace PhpFileHashMap\Test;

use PhpFileHashMap\FileHashMap;
use PhpFileHashMap\KeyNotExistsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HashMapTest extends TestCase
{
    protected const MAP_SIZE = 256;

    public static function dataProvider(): \generator
    {
        yield ['stringValueKey', 'stringValue'];
        yield ['intValueKey', 1];
        yield ['objectValueKey', new \stdClass()];
        yield ['arrayValueKey', ['stringValue', 1, new \stdClass()]];
        yield ['boolValueKey', true];
        yield ['boolValueKey', false];
        yield ['nullValueKey', null];
    }

    #[DataProvider('dataProvider')]
    public function testSetGet(string $key, mixed $value): void
    {
        $hashMap = new FileHashMap(self::MAP_SIZE);
        $hashMap->set($key, $value);
        $this->assertEquals($value, $hashMap->get($key));

        $hashMap->clear();
    }

    #[DataProvider('dataProvider')]
    public function testRemove(string $key, mixed $value): void
    {
        $hashMap = new FileHashMap(self::MAP_SIZE);
        $hashMap->set($key, $value);
        $this->assertEquals($value, $hashMap->get($key));

        $hashMap->remove($key);

        $this->expectException(KeyNotExistsException::class);
        $hashMap->get($key);

        $hashMap->clear();
    }

    #[DataProvider('dataProvider')]
    public function testHas(string $key, mixed $value): void
    {
        $hashMap = new FileHashMap(self::MAP_SIZE);
        $hashMap->set($key, $value);
        $this->assertEquals($value, $hashMap->get($key));
        $this->assertTrue($hashMap->has($key));
        $hashMap->remove($key);
        $this->assertFalse($hashMap->has($key));

        $hashMap->clear();
    }

    public function testCount(): void
    {
        $hashMap = new FileHashMap(self::MAP_SIZE);

        $key = 'key';
        $value = 'value';

        $this->assertEquals(0, $hashMap->count());
        $hashMap->set($key, $value);
        $this->assertEquals(1, $hashMap->count());
        $hashMap->set($key, $value);
        $this->assertEquals(1, $hashMap->count());
        $hashMap->set($key . '2', $value);
        $this->assertEquals(2, $hashMap->count());
        $hashMap->remove($key . '2');
        $this->assertEquals(1, $hashMap->count());

        $hashMap->clear();

        $this->assertEquals(0, $hashMap->count());
    }

    public function testKeys(): void
    {
        $hashMap = new FileHashMap(self::MAP_SIZE);

        $keys = ['key1', 'key2', 'key3'];
        foreach ($keys as $key) {
            $hashMap->set($key, true);
        }

        $hashMapKeys = [];
        foreach ($hashMap->keys() as $key) {
            $hashMapKeys[] = $key;
        }
        sort($keys);
        sort($hashMapKeys);
        $this->assertEquals($keys, $hashMapKeys);

        $hashMap->remove('key1');
        unset($keys[0]);

        $hashMapKeys = [];
        foreach ($hashMap->keys() as $key) {
            $hashMapKeys[] = $key;
        }
        sort($keys);
        sort($hashMapKeys);
        $this->assertEquals($keys, $hashMapKeys);
    }

    public function testValues(): void
    {
        $hashMap = new FileHashMap(self::MAP_SIZE);

        $data = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'];
        foreach ($data as $key => $value) {
            $hashMap->set($key, $value);
        }

        $hashMapValues = [];
        foreach ($hashMap->values() as $value) {
            $hashMapValues[] = $value;
        }
        sort($data);
        sort($hashMapValues);
        $this->assertEquals($data, $hashMapValues);

        $hashMap->remove('key1');
        unset($data[0]);

        $hashMapValues = [];
        foreach ($hashMap->values() as $value) {
            $hashMapValues[] = $value;
        }
        sort($data);
        sort($hashMapValues);
        $this->assertEquals($data, $hashMapValues);
    }

    public function testDefrag(): void
    {
        $hashMap = new FileHashMap(self::MAP_SIZE);
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        foreach ($data as $key => $value) {
            $hashMap->set($key, $value);
        }

        $hashMap->remove('key1');
        $hashMap->defrag();

        $this->assertEquals($data['key2'], $hashMap->get($key));
        $this->assertFalse($hashMap->has('key1'));
    }

    public function testCollisions(): void
    {
        $hashMap = $this->getMockBuilder(FileHashMap::class)
            ->onlyMethods(['getIndexByKey'])
            ->setConstructorArgs([self::MAP_SIZE])
            ->getMock();

        $hashMap->method('getIndexByKey')
            ->willReturn(42);

        $data = ['key1' => 'value1', 'key2' => 'value2', 'key3' => 'value3'];
        foreach ($data as $key => $value) {
            $hashMap->set($key, $value);
        }

        foreach ($data as $key => $value) {
            $this->assertEquals($value, $hashMap->get($key));
        }

        $data['key2'] = 'value22';
        $hashMap->set('key2', $data['key2']);
        foreach ($data as $key => $value) {
            $this->assertEquals($value, $hashMap->get($key));
        }

        $hashMap->remove('key2');
        unset($data['key2']);
        foreach ($data as $key => $value) {
            $this->assertEquals($value, $hashMap->get($key));
        }

        $data['key2'] = 'value222';
        $hashMap->set('key2', $data['key2']);
        foreach ($data as $key => $value) {
            $this->assertEquals($value, $hashMap->get($key));
        }

        $data['key4'] = 'value4';
        $hashMap->set('key4', $data['key4']);
        foreach ($data as $key => $value) {
            $this->assertEquals($value, $hashMap->get($key));
        }
    }
}