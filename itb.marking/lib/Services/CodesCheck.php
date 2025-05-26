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
    public function check(array $codes, ?string $fiscalDriveNumber = null): CodesCheckResult
    {
        if(empty($codes)) {
            throw new \RuntimeException("The codes array is empty");
        }
        try {
            $result = $this->getResultCheckCodes($this->cdnService->getCdn(), $codes, $fiscalDriveNumber);
            $this->saveInDb($result);
            return $result;
        } catch (TooManyRequestsException | TransborderCheckServiceUnavailableException $e) {
            $result = $this->getResultCheckCodes($this->cdnService->getCdn(true), $codes, $fiscalDriveNumber);
            $this->saveInDb($result);
            return $result;
        } catch (\Exception $e) {
            $this->log(fn() => $this->logger->error("Error checking codes: " . $e->getMessage(), $codes));
            throw $e;
        }
    }

    protected function getResultCheckCodes(Hosts $hosts, array $codes, ?string $fiscalDriveNumber = null): CodesCheckResult
    {
        if ($hosts->transborderServiceUnavailable) {
            $this->log(fn() => $this->logger->warning("Cross-border code verification service is not available.", $codes));
            return CodesCheckResult::transborderUnavailable($codes);
        }
        $lastException  = null;
        foreach ($hosts->getHosts() as $host) {
            try {
                $response = $this->retryWithTokenRefresh(fn() => $this->makeRequest($host, $codes, $fiscalDriveNumber));
                if (!empty($response['codes'])) {
                    return new CodesCheckResult($response);
                }
            } catch (\Exception $e) {
                $this->log(fn() => $this->logger->warning("error checking code on host - {$host->url}"));
                $lastException  = $e;
            }

        }
        throw $lastException ?? new \RuntimeException("Unable to verify codes on all hosts.");
    }

    private function makeRequest(Host $host, array $codes, ?string $fiscalDriveNumber = null)
    {
        return $this->post(new Uri("{$host->url}/api/v4/true-api/codes/check"), $this->getData($codes, $fiscalDriveNumber), $this->getHeaders());
    }

    protected function saveInDb(CodesCheckResult $result): void
    {
        try {
            $this->codeCheckRepository->save($result);
        } catch (\Exception $e) {
            $this->log(fn() => $this->logger->error("error in saveInDb.", ['message' => $e->getMessage(),'result' => $result]));
            throw $e;
        }
    }

    private function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-API-KEY' => $this->getAccessToken(),
        ];
    }

    private function getData(array $codes, ?string $fiscalDriveNumber = null): mixed
    {
        $data['codes'] = $codes;
        if($fiscalDriveNumber){
            $data['fiscalDriveNumber'] = $fiscalDriveNumber;    
        }
        return Json::encode($data);
    }
}
