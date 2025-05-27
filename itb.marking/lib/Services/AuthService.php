<?php

namespace Itb\Marking\Services;

use Bitrix\Main\Web\Json;
use Bitrix\Main\Web\Uri;
use Itb\Marking\Entity\CacheSettings;
use Itb\Marking\Exceptions\ClientUnathorizedException;
use Psr\Log\LoggerInterface;

class AuthService extends ApiService
{
    private ?string $token = null;
    private readonly bool $authByApi;
    private CacheSettings $cacheSettings;
    private const BASE_TEST_URL = "https://markirovka.sandbox.crptech.ru";
    private const BASE_URL = "https://markirovka.crpt.ru";

    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct($logger);
        $this->authByApi = $this->options->oauthKey !== '';
        if(!$this->authByApi){
            $this->token = $this->options->token;
        }
        $this->cacheSettings = new CacheSettings(1800, 'marking_access_token', '/marking/token');
    }

    /** 
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function getAccessToken(): string
    {
        if (!$this->token) {
            $this->setToken();
        }
        return $this->token;
    }

    /** 
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function refreshToken()
    {
        $this->setToken(true);
    }

    protected function retryWithTokenRefresh(callable $callback)
    {
        if (!$this->authByApi) {
            return $callback();
        }

        try {
            return $callback();
        } catch (ClientUnathorizedException) {
            $this->refreshToken();
            return $callback();
        }
    }

    private function setToken(bool $isRefresh = false): void
    {
        if(!$this->authByApi) return;
        $result = [];

        if ($isRefresh) {
            $this->cache->clean($this->cacheSettings->key, $this->cacheSettings->dir);
        }

        $this->makeRequest();

        if (!isset($result['access_token'], $result['expires_in'])) {
            throw new \RuntimeException('Error getting token');
        }

        if ($result['expires_in'] && $result['expires_in'] < time()) {
            $this->setToken(true);
            return;
        }

        $this->token = $result['access_token'];
    }

    private function makeRequest()
    {
        $baseUrl = $this->options->isTest ? self::BASE_TEST_URL : self::BASE_URL;
        return $this->post(new Uri("{$baseUrl}/api/v3/true-api/auth/permissive-access"), $this->getData(), $this->getHeaders(), $this->cacheSettings);
    }

    private function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function getData(): mixed
    {
        return Json::encode([
            'data' => $this->options->oauthKey,
        ]);
    }
}
