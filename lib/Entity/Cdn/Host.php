<?php
namespace Itb\Marking\Entity\Cdn;

class Host
{
    public readonly string $url;
    public int $avg = 0;
    public bool $blocked = false;
    public int $blockedTimestamp = 0;

    public function __construct(array $host)
    {
        $this->url = $host['host'];
    }

    public function setBlocked(): static
    {
        $this->blocked = true;
        $this->blockedTimestamp = time();
        return $this;
    }

    public function isBlocked(): bool
    {
        if (!$this->blocked) {
            return false;
        }

        if (time() - $this->blockedTimestamp > 15 * 60) {
            $this->blocked = false;
            $this->blockedTimestamp = 0;
            return false;
        }

        return true;
    }
}
