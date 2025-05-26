<?php

namespace Itb\Marking;

use Bitrix\Main\Application;
use Bitrix\Main\Web\Json;
use Itb\Marking\Entity\Codes\CodesCheckResult;

class CodeCheckRepository
{
    /**
     * @return null|CodesCheckResult[] сгруппированы по id запросов для каждого кода
     */
    public function findByCisList(array $cisList): ?array
    {
        if (empty($cisList)) {
            return null;
        }

        $result = CodeCheckTable::getList([
            'filter' => ['=CIS' => $cisList],
            'select' => [
                'ID',
                'CIS',
                'JSON_RESULT',
                'RESPONSE_ID',
                'RESPONSE_REQ_ID' => 'RESPONSE.REQ_ID',
                'RESPONSE_REQ_TIMESTAMP' => 'RESPONSE.REQ_TIMESTAMP',
                'RESPONSE_RESPONSE_CODE' => 'RESPONSE.RESPONSE_CODE',
                'RESPONSE_DESCRIPTION' => 'RESPONSE.DESCRIPTION',
                'RESPONSE_TRANSBORDER_SERVICE_UNAVAILABLE' => 'RESPONSE.TRANSBORDER_SERVICE_UNAVAILABLE',
            ]
        ]);

        $groupedByResponse = [];

        while ($row = $result->fetch()) {
            $responseId = $row['RESPONSE_ID'] ?? 'null';

            if (!isset($groupedByResponse[$responseId])) {
                $groupedByResponse[$responseId] = [
                    'reqId' => $row['RESPONSE_REQ_ID'],
                    'reqTimestamp' => $row['RESPONSE_REQ_TIMESTAMP'],
                    'responseCode' => $row['RESPONSE_RESPONSE_CODE'],
                    'description' => $row['RESPONSE_DESCRIPTION'],
                    'transborderServiceUnavailable' => (bool)$row['RESPONSE_TRANSBORDER_SERVICE_UNAVAILABLE'],
                    'codes' => [],
                ];
            } else {
                $groupedByResponse[$responseId]['transborderServiceUnavailable'] |= (bool)$row['RESPONSE_TRANSBORDER_SERVICE_UNAVAILABLE'];
            }

            $json = Json::decode($row['JSON_RESULT']);
            $groupedByResponse[$responseId]['codes'][$row['CIS']] = $json;
        }

        if (empty($groupedByResponse)) {
            return null;
        }

        $results = [];

        foreach ($groupedByResponse as $group) {
            if ($group['transborderServiceUnavailable']) {
                $results[] = CodesCheckResult::transborderUnavailable(array_keys($group['codes']));
            } else {
                $results[] = new CodesCheckResult([
                    'reqId' => $group['reqId'],
                    'reqTimestamp' => $group['reqTimestamp'],
                    'codes' => $group['codes'],
                    'code' => $group['responseCode'],
                    'description' => $group['description'],
                ]);
            }
        }

        return $results;
    }


    public function findByCis(string $cis): ?CodesCheckResult
    {
        if (empty($cis)) {
            return null;
        }

        $row = CodeCheckTable::getList([
            'filter' => ['=CIS' => $cis],
            'select' => [
                'ID',
                'CIS',
                'JSON_RESULT',
                'RESPONSE_ID',
                'RESPONSE_REQ_ID' => 'RESPONSE.REQ_ID',
                'RESPONSE_REQ_TIMESTAMP' => 'RESPONSE.REQ_TIMESTAMP',
                'RESPONSE_RESPONSE_CODE' => 'RESPONSE.RESPONSE_CODE',
                'RESPONSE_DESCRIPTION' => 'RESPONSE.DESCRIPTION',
                'RESPONSE_TRANSBORDER_SERVICE_UNAVAILABLE' => 'RESPONSE.TRANSBORDER_SERVICE_UNAVAILABLE',
            ],
            'limit' => 1,
        ])->fetch();

        if (!$row) {
            return null;
        }

        $json = Json::decode($row['JSON_RESULT']);
        $codes = [$row['CIS'] => $json];

        if ((bool)$row['RESPONSE_TRANSBORDER_SERVICE_UNAVAILABLE']) {
            return CodesCheckResult::transborderUnavailable([$row['CIS']]);
        }

        return new CodesCheckResult([
            'reqId' => $row['RESPONSE_REQ_ID'],
            'reqTimestamp' => $row['RESPONSE_REQ_TIMESTAMP'],
            'codes' => $codes,
            'code' => $row['RESPONSE_RESPONSE_CODE'],
            'description' => $row['RESPONSE_DESCRIPTION'],
        ]);
    }

    public function save(CodesCheckResult $result): void
    {
        $connection = Application::getConnection();
        $connection->startTransaction();

        try {
            $existingRows = CodeCheckTable::getList([
                'filter' => ['=CIS' => array_keys($result->codes)],
                'select' => ['ID', 'CIS', 'RESPONSE_ID'],
            ]);

            $existingByCis = [];
            $responseId = null;

            while ($row = $existingRows->fetch()) {
                $existingByCis[$row['CIS']] = $row;
                if ($responseId === null && !empty($row['RESPONSE_ID'])) {
                    $responseId = (int)$row['RESPONSE_ID'];
                }
            }
            if ($responseId === null) {
                $addResult = CodeCheckResponseTable::add([
                    'RESPONSE_CODE' => $result->code,
                    'DESCRIPTION' => $result->description,
                    'REQ_ID' => $result->reqId,
                    'REQ_TIMESTAMP' => (string)$result->reqTimestamp,
                    'TRANSBORDER_SERVICE_UNAVAILABLE' => $result->transborderServiceUnavailable,
                ]);

                if (!$addResult->isSuccess()) {
                    throw new \Exception(
                        'Ошибка при добавлении ответа проверки кода: ' . implode(', ', $addResult->getErrorMessages())
                    );
                }

                $responseId = $addResult->getId();
            } else {
                CodeCheckResponseTable::update($responseId, [
                    'RESPONSE_CODE' => $result->code,
                    'DESCRIPTION' => $result->description,
                    'TRANSBORDER_SERVICE_UNAVAILABLE' => $result->transborderServiceUnavailable,
                ]);
            }

            foreach ($result->codes as $code) {
                $json = Json::encode($code->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

                if (isset($existingByCis[$code->cis])) {
                    CodeCheckTable::update($existingByCis[$code->cis]['ID'], [
                        'JSON_RESULT' => $json,
                        'RESPONSE_ID' => $responseId,
                    ]);
                } else {
                    $addResult = CodeCheckTable::add([
                        'CIS' => $code->cis,
                        'JSON_RESULT' => $json,
                        'RESPONSE_ID' => $responseId,
                    ]);

                    if (!$addResult->isSuccess()) {
                        throw new \Exception(
                            'Ошибка при добавлении кода: ' . implode(', ', $addResult->getErrorMessages())
                        );
                    }
                }
            }

            $connection->commitTransaction();
        } catch (\Throwable $e) {
            $connection->rollbackTransaction();
            throw $e;
        }
    }
}
