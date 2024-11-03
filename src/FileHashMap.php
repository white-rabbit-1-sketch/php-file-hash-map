<?php

namespace PhpFileHashMap;

use PhpFileHashMap;

/**
 * This file represents a file hash map implementation.
 *
 * The file structure consists of two main sections:
 * 1. **The first part** contains $mapSize integer cells, each representing an offset to the heap area.
 *    - These $mapSize cells are located at the beginning of the file, where each cell stores a pointer (offset) to a
 *      start of data of corresponding bucket chain in the heap section.
 *    - The first part has the size: `$mapSize * INT_SIZE`, where each cell is of type `INT`.
 *
 * 2. **The heap area** contains:
 *    - The first two integers store statistics about the number of active and deleted buckets (INT and INT).
 *    - The remaining space in the heap is used for the actual bucket data. Each bucket has the following structure:
 *      - **State** (deleted or active), represented as an integer.
 *      - **Next bucket pointer** (offset in the heap) to the next bucket in the chain (INT).
 *      - **Key size** (integer representing the size of the key).
 *      - **Key** (string of the specified size).
 *      - **Value size** (integer representing the size of the value).
 *      - **Value** (serialized data of the specified size).
 *
 * The file layout is as follows:
 *
 * +-----------------------------------------------+
 * |                   File                        |
 * +-----------------------------------------------+
 * | First part: $mapSize * INT_SIZE (offset cells)|
 * +-----------------------------------------------+
 * | $mapSize INT cells, each containing an offset |
 * |  to the heap area (each offset is 8 bytes)    |
 * |  - Cell 0: Offset for bucket 0                |
 * |  - Cell 1: Offset for bucket 1                |
 * |  - Cell 2: Offset for bucket 2                |
 * |  ...                                          |
 * |  - Cell X: Offset for bucket X                |
 * +-----------------------------------------------+
 * | Next: Heap area                               |
 * +-----------------------------------------------+
 * | [Heap]                                        |
 * |  - First two INT values:                      |
 * |      - Active bucket count (INT)              |
 * |      - Deleted bucket count (INT)             |
 * |  - Bucket data:                               |
 * |      +-----------------------------------+    |
 * |      | Bucket 1                          |    |
 * |      +-----------------------------------+    |
 * |      | - State (deleted or active) (INT) |    |
 * |      | - Next bucket (heap offset) (INT) |    |
 * |      |   - Key size (INT)                |    |
 * |      |   - Key (string)                  |    |
 * |      |   - Value size (INT)              |    |
 * |      |   - Value (serialized data)       |    |
 * |      +-----------------------------------+    |
 * |      | Bucket 2                          |    |
 * |      +-----------------------------------+    |
 * |      |   ...                             |    |
 * |      +-----------------------------------+    |
 * +-----------------------------------------------+
 */
class FileHashMap implements HashMapInterface
{
    protected const INT_SIZE = 8;
    protected const BOOL_SIZE = 1;
    protected const BUCKET_INDEX_CELL_SIZE = self::INT_SIZE * 2;
    protected const STATISTICS_BLOCK_SIZE = self::INT_SIZE * 2;

    protected string $filePath;
    protected int $mapSize;
    protected bool $destroyDataFileOnShutdown;

    protected $fh;
    protected int $activeBucketsCount = 0;
    protected int $removedBucketsCount = 0;

    public function __construct(
        int $mapSize,
        ?string $filePath = null,
        bool $destroyDataFileOnShutdown = false
    ) {
        $this->mapSize = $mapSize;
        $this->filePath = $filePath ?? tempnam(sys_get_temp_dir(), 'hashmap.data');
        $this->destroyDataFileOnShutdown = $destroyDataFileOnShutdown;

        $this->openDataFile();
    }

    public function __destruct()
    {
        $this->closeDataFile();

        if ($this->destroyDataFileOnShutdown) {
            $this->removeDataFile();
        }
    }

    public function set(string $key, mixed $value): void
    {
        if (!$key) {
            throw new \InvalidArgumentException('Key cannot be empty');
        }

        $bucketWasReplaced = false;

        // determine last bucket offset and remove existed bucket
        $cellOffset = $this->getCellOffset($this->getIndexByKey($key));
        fseek($this->fh, $cellOffset);
        $bucketOffset = $this->unpack('P', fread($this->fh, self::INT_SIZE));

        while ($bucketOffset) {
            fseek($this->fh, $bucketOffset);
            $bucketState = (bool) $this->unpack('C', fread($this->fh, self::BOOL_SIZE));
            $nextBucketOffset = $this->unpack('P', fread($this->fh, self::INT_SIZE));

            if ($bucketState) {
                $bucketKeySize = $this->unpack('P', fread($this->fh, self::INT_SIZE));
                $bucketKey = fread($this->fh, $bucketKeySize);
                if ($bucketKey == $key) {
                    fseek($this->fh, $bucketOffset);
                    fwrite($this->fh, pack('C', 0)); // set state to false

                    $this->removedBucketsCount++;
                    $bucketWasReplaced = true;
                }
            }

            if (!$nextBucketOffset) {
                break;
            }

            $bucketOffset = $nextBucketOffset;
        }

        // create new bucket
        $heapTopOffset = $this->getHeapTopOffset();
        fseek($this->fh, $heapTopOffset);
        fwrite($this->fh, pack('C', 1));
        fseek($this->fh, $heapTopOffset + self::BOOL_SIZE + self::INT_SIZE);
        fwrite($this->fh, pack('P', strlen($key)));
        fwrite($this->fh, $key);
        $value = $this->serialize($value);
        fwrite($this->fh, pack('P', strlen($value)));
        fwrite($this->fh, $value);

        // update pointer to a new bucket
        fseek($this->fh, $bucketOffset ? $bucketOffset + self::BOOL_SIZE : $cellOffset);
        fwrite($this->fh, pack('P', $heapTopOffset));

        if (!$bucketWasReplaced) {
            $this->activeBucketsCount++;
        }

        $this->writeBucketsStatistics();
    }

    public function get(string $key): mixed
    {
        if (!$key) {
            throw new \InvalidArgumentException('Key cannot be empty');
        }

        $bucket = null;

        $cellOffset = $this->getCellOffset($this->getIndexByKey($key));
        fseek($this->fh, $cellOffset);
        $bucketOffset = $this->unpack('P', fread($this->fh, self::INT_SIZE));

        while ($bucketOffset) {
            fseek($this->fh, $bucketOffset);
            $bucketState = (bool) $this->unpack('C', fread($this->fh, self::BOOL_SIZE));
            $bucketOffset = $this->unpack('P', fread($this->fh, self::INT_SIZE));

            if (!$bucketState) {
                continue;
            }

            $bucketKeySize = $this->unpack('P', fread($this->fh, self::INT_SIZE));
            $bucketKey = fread($this->fh, $bucketKeySize);

            if ($bucketKey == $key) {
                $bucketValueSize = $this->unpack('P', fread($this->fh, self::INT_SIZE));
                $bucketValue = fread($this->fh, $bucketValueSize);
                $bucket = new Bucket($key, $this->unserialize($bucketValue));

                break;
            }
        }

        if (!$bucket) {
            throw new KeyNotExistsException($key);
        }

        return $bucket->getValue();
    }

    public function remove(string $key): void
    {
        if (!$key) {
            throw new \InvalidArgumentException('Key cannot be empty');
        }

        $cellOffset = $this->getCellOffset($this->getIndexByKey($key));
        fseek($this->fh, $cellOffset);
        $bucketOffset = $this->unpack('P', fread($this->fh, self::INT_SIZE));

        while ($bucketOffset) {
            fseek($this->fh, $bucketOffset);
            $bucketState = (bool) $this->unpack('C', fread($this->fh, self::BOOL_SIZE));
            $nextBucketOffset = $this->unpack('P', fread($this->fh, self::INT_SIZE));

            if ($bucketState) {
                $bucketKeySize = $this->unpack('P', fread($this->fh, self::INT_SIZE));
                $bucketKey = fread($this->fh, $bucketKeySize);
                if ($bucketKey == $key) {
                    fseek($this->fh, $bucketOffset);
                    fwrite($this->fh, pack('C', 0)); // set state to false
                    $this->removedBucketsCount++;
                    $this->activeBucketsCount--;
                    $this->writeBucketsStatistics();

                    break;
                }
            }

            $bucketOffset = $nextBucketOffset;
        }
    }

    public function has(string $key): bool
    {
        if (!$key) {
            throw new \InvalidArgumentException('Key cannot be empty');
        }

        $hasBucket = false;

        $cellOffset = $this->getCellOffset($this->getIndexByKey($key));
        fseek($this->fh, $cellOffset);
        $bucketOffset = $this->unpack('P', fread($this->fh, self::INT_SIZE));

        while ($bucketOffset) {
            fseek($this->fh, $bucketOffset);
            $bucketState = (bool) $this->unpack('C', fread($this->fh, self::BOOL_SIZE));
            $bucketOffset = $this->unpack('P', fread($this->fh, self::INT_SIZE));

            if (!$bucketState) {
                continue;
            }

            $hasBucket = true;

            break;
        }

        return $hasBucket;
    }

    public function count(): int
    {
        return $this->activeBucketsCount;
    }

    public function clear(): void
    {
        $this->closeDataFile();
        $this->removeDataFile();
        $this->openDataFile();
    }

    public function keys(): \generator
    {
        $heapBottomOffset = $this->getHeapBottomOffset();

        $cellOffset = 0;
        while ($cellOffset < $heapBottomOffset) {
            fseek($this->fh, $cellOffset);
            $bucketOffset = $this->unpack('P', fread($this->fh, self::INT_SIZE));

            while ($bucketOffset) {
                fseek($this->fh, $bucketOffset);
                $bucketState = (bool) $this->unpack('C', fread($this->fh, self::BOOL_SIZE));
                $bucketOffset = $this->unpack('P', fread($this->fh, self::INT_SIZE));

                if (!$bucketState) {
                    continue;
                }

                $bucketKeySize = $this->unpack('P', fread($this->fh, self::INT_SIZE));
                $bucketKey = fread($this->fh, $bucketKeySize);

                yield $bucketKey;
            }

            $cellOffset += self::BUCKET_INDEX_CELL_SIZE;
        }
    }

    public function values(): \generator
    {
        $heapBottomOffset = $this->getHeapBottomOffset();

        $cellOffset = 0;
        while ($cellOffset < $heapBottomOffset) {
            fseek($this->fh, $cellOffset);
            $bucketOffset = $this->unpack('P', fread($this->fh, self::INT_SIZE));

            while ($bucketOffset) {
                fseek($this->fh, $bucketOffset);
                $bucketState = (bool) $this->unpack('C', fread($this->fh, self::BOOL_SIZE));
                $nextBucketOffset = $this->unpack('P', fread($this->fh, self::INT_SIZE));

                if ($bucketState) {
                    $bucketKeySize = $this->unpack('P', fread($this->fh, self::INT_SIZE));
                    fseek($this->fh, $bucketOffset + self::BOOL_SIZE + self::INT_SIZE + self::INT_SIZE + $bucketKeySize);
                    $bucketValueSize = $this->unpack('P', fread($this->fh, self::INT_SIZE));
                    $bucketValue = fread($this->fh, $bucketValueSize);

                    yield $this->unserialize($bucketValue);
                }

                $bucketOffset = $nextBucketOffset;
            }

            $cellOffset += self::BUCKET_INDEX_CELL_SIZE;
        }
    }

    public function defrag(): void
    {
        $tempFilePath = tempnam(sys_get_temp_dir(), 'hashmap.data');
        $tempHashMap = new self($this->mapSize, $tempFilePath);

        foreach ($this->keys() as $key) {
            $value = $this->get($key);
            $tempHashMap->set($key, $value);
        }

        $this->closeDataFile();
        $this->removeDataFile();
        rename($tempFilePath, $this->filePath);
        $this->openDataFile();
    }

    protected function getIndexByKey(string $key): int
    {
        $hash = hash('sha256', $key);
        $hashInt = unpack('P', substr($hash, 0, 8))[1];

        return abs($hashInt) % $this->mapSize;
    }

    protected function readBucketsStatistics(): void
    {
        fseek($this->fh, $this->getHeapBottomOffset());
        $this->activeBucketsCount = (int) $this->unpack('P', fread($this->fh, self::INT_SIZE));
        $this->removedBucketsCount = (int) $this->unpack('P', fread($this->fh, self::INT_SIZE));
    }

    protected function writeBucketsStatistics(): void
    {
        fseek($this->fh, $this->getHeapBottomOffset());
        fwrite($this->fh, pack('P', $this->activeBucketsCount));
        fwrite($this->fh, pack('P', $this->removedBucketsCount));
    }

    protected function openDataFile(): void
    {
        $this->fh = fopen($this->filePath, 'c+');
        if (!$this->fh) {
            throw new \RuntimeException(sprintf('Unable to open file: %s', $this->filePath));
        }

        $this->readBucketsStatistics();
    }

    protected function closeDataFile(): void
    {
        fclose($this->fh);
    }

    protected function removeDataFile(): void
    {
        unlink($this->filePath);
    }

    protected function getCellOffset(int $mapIndex): int
    {
        return $mapIndex * self::BUCKET_INDEX_CELL_SIZE;
    }

    protected function getHeapBottomOffset(): int
    {
        return $this->mapSize * self::BUCKET_INDEX_CELL_SIZE;
    }

    protected function getHeapTopOffset(): int
    {
        fseek($this->fh, 0, SEEK_END);
        $offset = ftell($this->fh);

        if ($offset < $this->mapSize * self::BUCKET_INDEX_CELL_SIZE + self::STATISTICS_BLOCK_SIZE) {
            $offset = $this->mapSize * self::BUCKET_INDEX_CELL_SIZE + self::STATISTICS_BLOCK_SIZE;
        }

        return $offset;
    }

    protected function unpack(string $format, string $string): mixed
    {
        return $string ? unpack($format, $string)[1] : null;
    }

    protected function serialize(mixed $data): string
    {
        return serialize($data);
    }

    protected function unserialize(string $data): mixed
    {
        return unserialize($data);
    }
}
