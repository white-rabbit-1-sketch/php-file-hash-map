<?php

namespace PhpFileHashMap;

interface HashMapInterface
{
    public function set(string $key, mixed $value): void;
    public function get(string $key): mixed;
    public function remove(string $key): void;
    public function has(string $key): bool;
    public function count(): int;
    public function clear(): void;
    public function keys(): \generator;
    public function values(): \generator;
}
