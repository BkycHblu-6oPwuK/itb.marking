<?php

namespace Itb\Marking\Entity\Cdn;

class Hosts
{
    /** @var Host[] */
    private array $hosts;
    public bool $transborderServiceUnavailable = false;

    public function __construct(array $hosts)
    {
        $this->hosts = array_map(fn($item) => new Host($item), $hosts);
    }

    /**
     * @return Host[] Список не заблокированных хостов, отсортированный по avg.
     */
    public function getHosts(): array
    {
        $activeHosts = array_filter($this->hosts, fn(Host $host) => !$host->isBlocked());

        usort($activeHosts, function (Host $a, Host $b) {
            if ($a->avg === 0) return 1;
            if ($b->avg === 0) return -1;
            return $a->avg <=> $b->avg;
        });

        return $activeHosts;
    }

    public function isAllBlocked(): bool
    {
        foreach ($this->hosts as $host) {
            if (!$host->isBlocked()) {
                return false;
            }
        }
        return true;
    }
}
