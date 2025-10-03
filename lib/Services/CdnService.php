<?php

namespace Itb\Marking\Services;

use Bitrix\Main\Web\Uri;
use Itb\Core\Dto\CacheSettingsDto;
use Itb\Marking\Entity\Cdn\Host;
use Itb\Marking\Entity\Cdn\Hosts;
use Itb\Marking\Exceptions\CdnTemporarilyUnavailableException;
use Itb\Marking\Exceptions\TooManyRequestsException;
use Itb\Marking\Exceptions\TransborderCheckServiceUnavailableException;
use Psr\Log\LoggerInterface;

class CdnService extends AuthService
{
    private CacheSettingsDto $cacheSettings;

    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct($logger);
        $this->cacheSettings = new CacheSettingsDto(3600 * 6, 'marking_cdn', '/marking/cdn');
    }

    public function getCdn(bool $isRefresh = false): Hosts
    {
        if ($isRefresh) {
            $this->cache->clean($this->cacheSettings->key, $this->cacheSettings->dir);
        }
        $hosts = $this->getCached($this->cacheSettings, function () {
            $hosts = $this->getHosts();
            $this->checkAllCdn($hosts);
            if ($hosts->isAllBlocked()) {
                $this->log(fn() => $this->logger->warning("All CDN hosts are blocked, we are trying to get and check again."));
                $hosts = $this->getHosts();
                $this->checkAllCdn($hosts);
            }
            if ($hosts->transborderServiceUnavailable) {
                $this->cacheSettings->abortCache = true;
            }
            return $hosts;
        });
        return $hosts;
    }

    private function checkAllCdn(Hosts $hosts): void
    {
        foreach ($hosts->getHosts() as $host) {
            try {
                $this->checkCdn($host);
            } catch (TransborderCheckServiceUnavailableException $e) {
                $this->log(fn() => $this->logger->warning("Cross-border code verification service is unavailable: " . $e->getMessage()));
                $hosts->transborderServiceUnavailable = true;
                break;
            } catch (TooManyRequestsException | CdnTemporarilyUnavailableException $e) {
                $this->log(fn() => $this->logger->warning("Host {$host->url} is blocked: " . $e->getMessage()));
                $host->setBlocked();
            } catch (\Throwable $e) {
                $this->log(fn() => $this->logger->warning("Host problem {$host->url}: " . $e->getMessage()));
            }
        }
    }

    protected function getHosts(): Hosts
    {
        $result = $this->retryWithTokenRefresh(fn() => $this->makeHostsRequest());

        if (empty($result['hosts'])) {
            throw new \RuntimeException("Error getting hosts");
        }

        return new Hosts($result['hosts']);
    }

    protected function checkCdn(Host $host): void
    {
        try {
            $this->attemptCheckCdn($host);
        } catch (TooManyRequestsException | CdnTemporarilyUnavailableException | TransborderCheckServiceUnavailableException) {
            $this->attemptCheckCdn($host);
        }
    }

    private function attemptCheckCdn(Host $host): void
    {
        $result = $this->retryWithTokenRefresh(fn() => $this->makeCheckCdnRequest($host));
        if (!isset($result['avgTimeMs'])) {
            throw new \RuntimeException("attemptCheckCdn error");
        }
        $host->avg = (int)$result['avgTimeMs'];
    }

    private function makeHostsRequest()
    {
        return $this->get(new Uri("{$this->options->baseUrl}/api/v4/true-api/cdn/info"), null, [
            'Content-Type' => 'application/json',
            'X-API-KEY' => $this->getAccessToken(),
        ]);
    }
    private function makeCheckCdnRequest(Host $host)
    {
        return $this->get(new Uri("{$host->url}/api/v4/true-api/cdn/health/check"), null, [
            'Content-Type' => 'application/json',
            'Connection' => 'close',
            'X-API-KEY' => $this->getAccessToken(),
        ]);
    }
}
