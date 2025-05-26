<?php

namespace Itb\Marking\Services;

use Bitrix\Main\Web\Json;
use Bitrix\Main\Web\Uri;
use Itb\Marking\CodeCheckRepository;
use Itb\Marking\Entity\Cdn\Host;
use Itb\Marking\Entity\Cdn\Hosts;
use Itb\Marking\Entity\Codes\CodesCheckResult;
use Itb\Marking\Exceptions\TooManyRequestsException;
use Itb\Marking\Exceptions\TransborderCheckServiceUnavailableException;
use Psr\Log\LoggerInterface;

class CodesCheck extends AuthService
{
    protected readonly CdnService $cdnService;
    protected readonly CodeCheckRepository $codeCheckRepository;

    public function __construct(?LoggerInterface $logger = null, ?CdnService $cdnService = null, ?CodeCheckRepository $codeCheckRepository = null)
    {
        if (!$cdnService) {
            $cdnService = new CdnService($logger);
        }
        if (!$codeCheckRepository) {
            $codeCheckRepository = new CodeCheckRepository();
        }
        $this->cdnService = $cdnService;
        $this->codeCheckRepository = $codeCheckRepository;
        parent::__construct($logger);
    }

    /**
     * @param string[] $codes
     */
    public function check(array $codes): CodesCheckResult
    {
        if(empty($codes)) {
            throw new \RuntimeException("The codes array is empty");
        }
        try {
            return $this->getResultCheckCodes($this->cdnService->getCdn(), $codes);
        } catch (TooManyRequestsException | TransborderCheckServiceUnavailableException $e) {
            return $this->getResultCheckCodes($this->cdnService->getCdn(true), $codes);
        } catch (\Exception $e) {
            $this->log(fn() => $this->logger->error("Error checking codes: " . $e->getMessage(), $codes));
            throw $e;
        }
    }

    protected function getResultCheckCodes(Hosts $hosts, array $codes): CodesCheckResult
    {
        if ($hosts->transborderServiceUnavailable) {
            $this->log(fn() => $this->logger->warning("Cross-border code verification service is not available.", $codes));
            $result = CodesCheckResult::transborderUnavailable($codes);
            $this->saveInDb($result);
            return $result;
        }
        foreach ($hosts->getHosts() as $host) {
            $result = $this->retryWithTokenRefresh(fn() => $this->makeRequest($host, $codes));
            if (!empty($result['codes'])) {
                $result = new CodesCheckResult($result);
                $this->saveInDb($result);
                return $result;
            }
        }
        throw new \RuntimeException("Unable to verify codes on all hosts.");
    }

    private function makeRequest(Host $host, array $codes)
    {
        return $this->post(new Uri("{$host->url}/api/v4/true-api/codes/check"), $this->getData($codes), $this->getHeaders());
    }

    protected function saveInDb(CodesCheckResult $result): void
    {
        $this->codeCheckRepository->save($result);
    }

    private function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-API-KEY' => $this->getAccessToken(),
        ];
    }

    private function getData(array $codes): mixed
    {
        return Json::encode([
            'codes' => $codes,
        ]);
    }
}
