<?php

namespace Itb\Marking;

use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Web\Uri;
use Itb\Marking\Enum\Method;
use Itb\Marking\Exceptions\CdnTemporarilyUnavailableException;
use Itb\Marking\Exceptions\ClientException;
use Itb\Marking\Exceptions\ClientUnathorizedException;
use Itb\Marking\Exceptions\TooManyRequestsException;
use Itb\Marking\Exceptions\TransborderCheckServiceUnavailableException;

class Client extends HttpClient
{
    protected mixed $postData = null;

    public function __construct(?array $options = null)
    {
        parent::__construct($options);
    }

    public function setPostData(mixed $data)
    {
        $this->postData = $data;
        return $this;
    }

    public function getPostData(): mixed
    {
        return $this->postData;
    }

    /**
     * @throws \Throwable;
     */
    public function request(Method $method, Uri $uri): static
    {
        match ($method) {
            Method::GET => $this->get($uri->getLocator()),
            Method::POST => $this->post($uri->getLocator(), $this->getPostData()),
            default => null
        };
        $this->handleResult();
        return $this;
    }

    protected function handleResult(): void
    {
        $status = $this->getStatus();
        if ($status === 401) throw new ClientUnathorizedException('Client unathorized');
        if ($status === 429) throw new TooManyRequestsException("rate limit exceeded");
        if ($status >= 500 && $status < 600) {
            $errorCode = \Bitrix\Main\Web\Json::decode($this->getResult())['code'] ?? null;

            if ($errorCode == 5000) {
                throw new TransborderCheckServiceUnavailableException("Transborder check service is unavailable");
            }

            throw new CdnTemporarilyUnavailableException("CDN temporarily unavailable");
        }
        if (!$this->isSuccess()) throw new ClientException('HTTP Request Failed');
    }

    public function isSuccess(): bool
    {
        $status = $this->getStatus();
        return $status > 0 && $status < 300;
    }
}
