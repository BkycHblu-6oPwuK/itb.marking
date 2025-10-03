<?php

namespace Itb\Marking\Services;

use Bitrix\Main\Web\Json;
use Bitrix\Main\Web\Uri;
use Itb\Core\Dto\CacheSettingsDto;
use Itb\Marking\Client;
use Itb\Marking\Enum\Method;
use Itb\Marking\Exceptions\ClientException;
use Itb\Marking\Logger;
use Itb\Marking\Options;
use Psr\Log\LoggerInterface;
use Itb\Core\Traits\Cacheable;

abstract class ApiService
{
    use Cacheable;
    protected readonly Client $client;
    protected readonly Options $options;
    protected readonly LoggerInterface $logger;

    /**
     * @param null|array $options для http клиента bitrix
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->client = new Client();
        $this->options = Options::getInstance();

        if ($this->options->isTest) {
            $this->client->disableSslVerification();
        }

        if (!$logger) {
            $logger = new Logger;
        }
        $this->logger = $logger;
    }

    /**
     * @param Uri $uri адрес запроса
     * @param null|array $data ключ-значение
     * @param null|array $headers ключ-значение
     */
    protected function get(Uri $uri, ?array $data = null, ?array $headers = null, ?CacheSettingsDto $cacheSettings = null): array
    {
        if ($data) $uri->addParams($data);
        if ($headers) $this->client->setHeaders($headers);
        return $this->request(Method::GET, $uri, $cacheSettings);
    }

    /**
     * @param Uri $uri адрес запроса
     * @param mixed $data
     * @param null|array $headers ключ-значение
     */
    protected function post(Uri $uri, mixed $data = null, ?array $headers = null, ?CacheSettingsDto $cacheSettings = null): array
    {
        $this->client->setPostData($data);
        if ($headers) $this->client->setHeaders($headers);
        return $this->request(Method::POST, $uri, $cacheSettings);
    }

    private function request(Method $method, Uri $uri, ?CacheSettingsDto $cacheSettings = null): array
    {
        try {
            $cacheSettings ??= new CacheSettingsDto;

            $result = $this->getCached($cacheSettings, function () use ($method, $uri) {
                return $this->handleResult($this->client->request($method, $uri)->getResult());
            });

            return $result;
        } catch (ClientException $e) {
            $error = $this->client->getError();
            $result = $this->handleResult($this->client->getResult());
            if (!empty($result)) {
                $error = [
                    'http_error' => $error,
                    'api_error' => $result,
                ];
            }
            $error['status'] = $this->client->getStatus();
            $this->log(fn() => $this->logger->error($e->getMessage(), $error));
            throw $e;
        } catch (\Throwable $e) {
            $this->log(fn() => $this->logger->error($e->getMessage()));
            throw $e;
        }
    }

    public function log(callable $callback): void
    {
        if ($this->options->logsEnable) {
            $callback();
        }
    }

    protected function handleResult(mixed $result): array
    {
        try {
            return Json::decode($result);
        } catch (\Exception $e) {
            if ($this->options->logsEnable) {
                $this->logger->error($e->getMessage());
            }
        }
        return [];
    }
}
