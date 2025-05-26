<?php

namespace Itb\Marking\Entity\Codes;

class CodesCheckResult
{
    /** Результат обработки операции */
    public readonly string $code;
    /** Текстовое описание результата выполнения метода */
    public readonly string $description;
    /** @var Code[] Список КМ */
    public readonly array $codes;
    /** Уникальный идентификатор запроса */
    public readonly string $reqId;
    /** Дата и время формирования запроса */
    public readonly int $reqTimestamp;
    /** доступность сервиса трансграничной проверки кодов */
    public readonly bool $transborderServiceUnavailable;

    public function __construct(array $result, bool $transborderServiceUnavailable = false)
    {
        $this->code = $result['code'] ?? '';
        $this->description = $result['description'] ?? '';
        $this->transborderServiceUnavailable = $transborderServiceUnavailable;
        $this->reqId = $result['reqId'] ?? '';
        $this->reqTimestamp = $result['reqTimestamp'] ?? 0;
        $codes = [];
        foreach ($result['codes'] ?? [] as $item) {
            if ($item instanceof Code) {
                $codes[$item->cis] = $item;
                continue;
            }
            $codes[$item['cis']] = new Code($item);
        }
        $this->codes = $codes;
    }

    public static function transborderUnavailable(array $cisList): static
    {
        $codes = [];
        foreach ($cisList as $cis) {
            $codes[] = Code::transborderUnavailable($cis);
        }
        return new static(['reqId' => '', 'reqTimestamp' => 0, 'codes' => $codes], true);
    }

    public function get(string $cis): ?Code
    {
        return $this->codes[$cis] ?? null;
    }
}
