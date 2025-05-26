<?php
namespace Itb\Marking\Entity;

class CacheSettings
{
    public int $time;
    public readonly string $key;
    public readonly string $dir;
    public bool $fromCache = false;
    public bool $abortCache = false;

    public function __construct(int $time = 0, string $key = '', string $dir = '')
    {
        $this->time = $time;
        $this->key = $key;
        $this->dir = $dir;
    }
}