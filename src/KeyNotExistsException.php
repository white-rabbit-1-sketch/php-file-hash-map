<?php

namespace PhpFileHashMap;

class KeyNotExistsException extends \Exception
{
    protected string $key;

    public function __construct(string $key)
    {
        $this->key = $key;

        parent::__construct(sprintf('Key "%s" does not exist', $key));
    }
}