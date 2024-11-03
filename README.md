# Php File Hash Map

![Latest Version](https://img.shields.io/github/v/tag/white-rabbit-1-sketch/php-file-hash-map)
![Phpunit](https://img.shields.io/github/actions/workflow/status/white-rabbit-1-sketch/php-file-hash-map/.github%2Fworkflows%2Fphpunit.yml)
[![codecov](https://codecov.io/github/white-rabbit-1-sketch/php-file-hash-map/graph/badge.svg?token=3TJ9GL4OAS)](https://codecov.io/github/white-rabbit-1-sketch/php-file-hash-map)

![Banner](readme/assets/img/banner.webp)


`PhpFileHashMap` is a PHP implementation of a file-based hash map that stores key-value pairs in a binary file. The hash map operates on a file system level, which makes it suitable for handling large amounts of data with minimal memory usage. This implementation allows persisting hash map data to a file while providing standard hash map operations like `set`, `get`, `remove`, and more.

---

⭐️ Star the Project

If you found this project useful, please consider giving it a star! 🌟 Your support helps improve the project and motivates us to keep adding new features and improvements. Thank you! 🙏

---

## Table of Contents

- [Php File Hash Map](#php-file-hash-map)
- [Features](#features)
- [Performance Benchmarks](#performance-benchmarks)
- [Installation](#installation)
- [Usage](#usage)
  - [Creating a Hash Map](#creating-a-hash-map)
  - [Adding Data](#adding-data)
  - [Retrieving Data](#retrieving-data)
  - [Removing Data](#removing-data)
  - [Checking for Key Existence](#checking-for-key-existence)
  - [Counting Active Buckets](#counting-active-buckets)
  - [Iterating Over Keys and Values](#iterating-over-keys-and-values)
  - [Clearing the Hash Map](#clearing-the-hash-map)
- [Nuances and Performance Considerations](#nuances-and-performance-considerations)
  - [Recommended Hash Map Size](#recommended-hash-map-size)
  - [Data File and Custom Location](#data-file-and-custom-location)
  - [Defragmentation](#defragmentation)
  - [Serialization](#serialization)
    - [Serialization Override](#serialization-override)
    - [Serialization of Closures](#serialization-of-closures)
- [Restrictions](#restrictions)
  - [Concurrent Access](#1-concurrent-access)
  - [Distributed Systems](#2-distributed-systems)
- [Why Choose This Library Over SQLite?](#why-choose-this-library-over-sqlite)
- [Data File Structure](#data-file-structure)
  - [Map Index Section](#1-map-index-section)
  - [Heap Section](#2-heap-section)
  - [File Layout Example](#file-layout-example)
- [Author and License](#author-and-license)

## Features

- **Persistent storage**: The hash map data is stored in a binary file, allowing data persistence even after the script execution ends.
- **Efficient memory usage**: Uses file storage to manage large datasets with low memory overhead.
- **Basic hash map operations**: Supports key-value insertion, retrieval, deletion, existence checks, and iteration.
- **Collision handling**: The hash map handles collisions by chaining multiple buckets in the file.

## Warning!
This is not a data storage solution and was never intended to be used as one. Essentially, it is an implementation of the hash map data structure with data stored on disk, and its current applicability is specifically within this context. But of course, you can use it as storage if it suits your task and you understand all the nuances.

## Performance Benchmarks

The performance of this file-based hash map may vary depending on the system configuration and the number of elements. On my MacBook Air M2, the hash map performed as follows (single thread):

- File Hash Map: 140k writes, 280-700k reads (depends on data/buffering)
- Redis: 25k writes, 20k reads
- Memcached: 24k writes, 30k reads
- MySQL with Hash Index: 6k writes, 15k reads
- Aerospike: 5k writes, 5k reads

## Installation

You can install `PhpFileHashMap` via Composer by adding the following to your `composer.json`:

```bash
composer require white-rabbit-1-sketch/php-file-hash-map
```

## Usage

### Creating a Hash Map
```php
use PhpFileHashMap\FileHashMap;

$hashMap = new FileHashMap(256); // Creates a hash map with a size of 256 buckets
```

### Adding Data
```php
$hashMap->set('key1', 'value1');
$hashMap->set('key2', 'value2');
```

### Retrieving Data
```php
$value = $hashMap->get('key1');
echo $value; // Outputs 'value1'
```

### Removing Data
```php
$hashMap->remove('key2');
```

### Checking for Key Existence
```php
if ($hashMap->has('key2')) {
    echo "Key exists!";
} else {
    echo "Key does not exist!";
}
```

### Counting Data
```php
echo $hashMap->count(); // Outputs the number of active buckets
```

### Iterating Over Keys and Values
```php
// Iterating over keys
foreach ($hashMap->keys() as $key) {
    echo $key . "\n";
}

// Iterating over values
foreach ($hashMap->values() as $value) {
    echo $value . "\n";
}
```

Both the `keys()` and `values()` methods iterate over all the elements in the hash map and return the respective keys and values.

- **`keys()`**: Returns an iterator of all the keys in the hash map.
- **`values()`**: Returns an iterator of all the values in the hash map.

Both of these methods require scanning the entire hash map, including both the Map Index Section and the Heap Section, to collect the keys or values. This means that they need to read through all buckets, including any inactive (deleted) ones, and this can be **resource-intensive in terms of time** if the hash map contains a large number of entries or deleted buckets.

However, it’s important to note that these operations **are not memory-intensive**. Since the methods use **generators**, they do not load all keys or values into memory at once, making them **efficient in terms of memory usage**. Only one key or value is held in memory at a time during iteration.


### Clearing the Hash Map
```php
$hashMap->clear(); // Removes all keys and values
```


## Nuances and Performance Considerations

This file-based hash map efficiently resolves collisions by utilizing chaining (linked lists of buckets). However, as the number of collisions increases, the performance may degrade. This degradation becomes particularly noticeable during write operations (insertion and deletion). Therefore, to ensure optimal performance, it is recommended to keep the hash map at a reasonable size relative to the expected number of elements.

#### Recommended Hash Map Size

For best performance, the size of the hash map should be chosen based on the estimated number of elements you plan to store. A good rule of thumb is to set the map size to a value that is roughly **1.5 to 2 times larger than the expected number of elements**. This helps reduce the likelihood of collisions and ensures fast access times.

For example:
- For up to 10,000 elements, consider a map size of 16,000 to 20,000.
- For up to 100,000 elements, aim for a map size of 150,000 to 200,000.

By keeping the number of collisions low, you maintain fast read and write speeds, especially in the case of write-heavy workloads.

#### Data File and Custom Location

By default, the hash map automatically creates a data file in the system's temporary directory. This file is used to store the hash map's data persistently.

- **Default behavior**: The file will be created in the temporary directory (e.g., `/tmp` on Unix-based systems).
- **Customization**: You can override this behavior and specify your own file location by providing a custom file path when constructing the hash map instance.

```php
use PhpFileHashMap\FileHashMap;

$hashMap = new FileHashMap(256, destroyDataFileOnShutdown: true); // Deletes the file on shutdown
```

#### Defragmentation

When keys are removed from the hash map, the corresponding buckets are not physically deleted from the file. Instead, they are marked as deleted. This is done to avoid the performance cost of file operations, as physically deleting data would require shifting the file contents, which can be expensive.

However, over time, especially with many deletions, the file may accumulate a significant number of deleted buckets, which could reduce performance. In such cases, it is advisable to perform **defragmentation** to reclaim space and optimize the file layout.

The `defrag()` method reorganizes the entire hash map file by recalculating the entire structure from scratch. This includes removing any deleted buckets and restructuring the map for better performance.

```php
$hashMap->defrag();
```

Note: Defragmentation is a resource-intensive operation, especially for large hash maps, as it requires reading and rewriting the entire file. Therefore, it should be used carefully and ideally not too frequently.


#### Serialization

By default, this hash map uses PHP's built-in `serialize()` and `unserialize()` functions to handle the serialization of values stored in the map. This allows you to store any PHP data type, including objects, arrays, and other complex structures.

##### Serialization Override

The default methods for serializing and unserializing data are:

```php
protected function serialize(mixed $data): string
{
    return serialize($data);
}

protected function unserialize(string $data): mixed
{
    return unserialize($data);
}
```

These methods can be easily overridden if you need to use a different serialization format (e.g., JSON, MessagePack, etc.) or a custom approach. By overriding these methods, you can control how data is converted before being stored in the hash map and after being retrieved.

##### Serialization of Closures

To handle this, you can use the **`opis/closure`** library to serialize and unserialize closures.

To enable serialization of closures, you need to install the **`opis/closure`** library. This can be done via Composer:

```bash
composer require opis/closure
```
Once the library is installed, you can easily customize the serialization and unserialization methods of your hash map to handle closures. Here's an example of how to do it:

```php
use Opis\Closure\SerializableClosure;

class FileHashMapWithClosures extends FileHashMap
{
    // Override the serialize method to handle closures
    protected function serialize(mixed $data): string
    {
        return \Opis\Closure\serialize($data);
    }

    // Override the unserialize method to handle closures
    protected function unserialize(string $data): mixed
    {
        return \Opis\Closure\unserialize($data);
    }
}

```

## Restrictions

When using `PhpFileHashMap`, keep in mind the following limitations due to its file system-based storage:

### 1. Concurrent Access

If multiple processes attempt to access the same hash map file simultaneously, race conditions may occur. To ensure data integrity, **you must implement locking mechanisms** when working with the same file in parallel processes.

Locking is intentionally **not implemented in this library** to keep it lightweight and to give developers the freedom to choose their preferred locking strategy. Examples of possible solutions include:
- Using PHP's `flock()` function for file-level locks.
- Implementing inter-process locks via shared memory or database-backed mutexes.

### 2. Distributed Systems

This library does not handle synchronization in distributed environments. If you need to share the same hash map file across multiple machines, synchronization must be implemented externally.

Examples of how this can be addressed:
- Use a distributed file system (e.g., NFS, GlusterFS) with appropriate locking mechanisms.
- Employ a coordination service like **Zookeeper** for managing access and updates.

These restrictions are by design to maintain the simplicity and portability of `PhpFileHashMap`, leaving implementation details of complex infrastructure to the developer.

## Why Choose This Library Over SQLite?

- **Performance**: This library outperforms SQLite in terms of raw speed. Benchmark tests show it can handle **700,000 reads** and **140,000 writes** per second, while SQLite is limited to **70,000 reads** and **4,000 writes** per second. This makes it a better choice for high-performance applications that require fast access to key-value data.

- **Lightweight**: Unlike SQLite, which includes a full relational database engine, this library focuses purely on key-value storage. This minimalism reduces latency and avoids the overhead associated with SQL parsing and transaction management, making it faster and more efficient for simple use cases.

- **No Database Overhead**: SQLite is designed for relational data storage and comes with features that aren't needed for basic key-value storage. If all you need is a fast, persistent key-value store, this library eliminates the complexities of relational databases and provides a streamlined solution.

- **Customization and Control**: With this library, you have full control over the storage and retrieval logic. You can tailor it to meet your specific needs without being constrained by the rigid structure and limitations of SQLite.

In summary, if you need a **high-performance, simple key-value storage solution** without the overhead of a full-fledged database engine, this library offers a more optimized, flexible, and customizable alternative to SQLite.

## Data File Structure

The file structure of the hash map is designed to efficiently manage large amounts of data. It consists of two main sections: the **Map Index Section** and the **Heap Section**.

#### 1. **Map Index Section**

The Map Index Section is located at the beginning of the file. It contains a series of integers, each representing the offset of a bucket in the Heap Section. The number of entries in the index is equal to the number of buckets in the hash map.

- **Size**: `$mapSize * INT_SIZE`
- **Format**: The section contains a list of integers, each corresponding to the offset of a bucket in the heap.
    - Example: If you have a hash map with 256 buckets, this section will consist of 256 integers.

#### 2. **Heap Section**

The Heap Section contains all the actual data for the hash map’s buckets. Each bucket is a block of data that includes the following elements:

- **Bucket State (INT)**: An integer representing the state of the bucket. A value of `1` indicates that the bucket is active, and `0` indicates that the bucket is deleted.
- **Next Bucket Pointer (P)**: A pointer (offset in the heap) to the next bucket in the chain (used for handling collisions).
- **Key Size (INT)**: The size of the key in bytes.
- **Key (string)**: The key itself.
- **Value Size (INT)**: The size of the value in bytes.
- **Value (serialized data)**: The serialized value associated with the key.

Additionally, the Heap Section begins with two integers that hold the following data:

- **Active Bucket Count (INT)**: The number of active (non-deleted) buckets in the hash map.
- **Deleted Bucket Count (INT)**: The number of deleted buckets in the hash map.

The rest of the heap consists of individual buckets, which contain the serialized data for each key-value pair.

#### File Layout Example

```
+-----------------------------------------------+
|                   File                        |
+-----------------------------------------------+
| First part: $mapSize * INT_SIZE (offset cells)|
+-----------------------------------------------+
| $mapSize INT cells, each containing an offset |
| to the heap area (each offset is 8 bytes)     |
|  - Cell 0: Offset for bucket 0                |
|  - Cell 1: Offset for bucket 1                |
|  - Cell 2: Offset for bucket 2                |
|  ...                                          |
|  - Cell X: Offset for bucket X                |
+-----------------------------------------------+
| Next: Heap area                               |
+-----------------------------------------------+
| [Heap]                                        |
|  - First two INT values:                      |
|      - Active bucket count (INT)              |
|      - Deleted bucket count (INT)             |
|  - Bucket data:                               |
|      +-----------------------------------+    |
|      | Bucket 1                          |    |
|      +-----------------------------------+    |
|      | - State (deleted or active) (INT) |    |
|      | - Next bucket (heap offset) (INT) |    |
|      | - Key size (INT)                  |    |
|      | - Key (string)                    |    |
|      | - Value size (INT)                |    |
|      | - Value (serialized data)         |    |
|      +-----------------------------------+    |
|      | Bucket 2                          |    |
|      +-----------------------------------+    |
|      |   ...                             |    |
|      +-----------------------------------+    |
+-----------------------------------------------+

```

## Author and License

**Author**: Mikhail Chuloshnikov

**License**: MIT License

This library is released under the MIT License. See the [LICENSE](LICENSE) file for more details.
