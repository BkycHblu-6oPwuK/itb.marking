<?php

namespace Itb\Marking\Entity\Codes;

readonly class Code
{
    /** КМ из запроса */
    public string $cis;
    /** Результат проверки валидности структуры КМ */
    public bool $valid;
    /** КМ без крипто-подписи */
    public string $printView;
    /** Код товара */
    public string $gtin;
    /** Массив идентификаторов товарных групп */
    public array $groupIds;
    /** Результат проверки криптоподписи КМ */
    public bool $verified;
    /** Признак наличия кода */
    public bool $found;
    /** Признак ввода в оборот */
    public bool $realizable;
    /** Признак нанесения КИ на упаковку */
    public bool $utilised;
    /** Признак того, что розничная продажа продукции заблокирована по решению ОГВ */
    public bool $isBlocked;
    /** Дата и время истечения срока годности */
    public ?\DateTimeImmutable $expireDate;
    /** Дата производства продукции */
    public ?\DateTimeImmutable $productionDate;
    /** Код ошибки */
    public int $errorCode;
    /** Признак контроля прослеживаемости в товарной группе */
    public bool $isTracking;
    /** Признак вывода из оборота товара */
    public bool $sold;
    /** Тип упаковки */
    public string $packageType;
    /** ИНН производителя */
    public string $producerInn;
    /** Признак принадлежности табачной продукции к «серой зоне» */
    public bool $grayZone;
    /** Счётчик проданного и возвращённого товара */
    public int $soldUnitCount;
    /** Количество единиц товара в потребительской упаковке / Фактический объём / Фактический вес */
    public int $innerUnitCount;

    public function __construct(array $item)
    {
        $this->cis = $item['cis'] ?? '';
        $this->valid = $item['valid'] ?? false;
        $this->printView = $item['printView'] ?? '';
        $this->gtin = $item['gtin'] ?? '';
        $this->groupIds = $item['groupIds'] ?? [];
        $this->verified = $item['verified'] ?? false;
        $this->found = $item['found'] ?? false;
        $this->realizable = $item['realizable'] ?? false;
        $this->utilised = $item['utilised'] ?? false;
        $this->isBlocked = $item['isBlocked'] ?? false;
        $this->expireDate = isset($item['expireDate']) ? new \DateTimeImmutable($item['expireDate']) : null;
        $this->productionDate = isset($item['productionDate']) ? new \DateTimeImmutable($item['productionDate']) : null;
        $this->errorCode = $item['errorCode'] ?? 0;
        $this->isTracking = $item['isTracking'] ?? false;
        $this->sold = $item['sold'] ?? false;
        $this->packageType = $item['packageType'] ?? '';
        $this->producerInn = $item['producerInn'] ?? '';
        $this->grayZone = $item['grayZone'] ?? false;
        $this->soldUnitCount = $item['soldUnitCount'] ?? 0;
        $this->innerUnitCount = $item['innerUnitCount'] ?? 0;
    }

    public static function transborderUnavailable(string $cis): static
    {
        return new static(['cis' => $cis], true);
    }

    public function toArray(): array
    {
        return [
            'cis' => $this->cis,
            'valid' => $this->valid,
            'printView' => $this->printView,
            'gtin' => $this->gtin,
            'groupIds' => $this->groupIds,
            'verified' => $this->verified,
            'found' => $this->found,
            'realizable' => $this->realizable,
            'utilised' => $this->utilised,
            'isBlocked' => $this->isBlocked,
            'expireDate' => $this->expireDate?->format(DATE_ATOM),
            'productionDate' => $this->productionDate?->format(DATE_ATOM),
            'errorCode' => $this->errorCode,
            'isTracking' => $this->isTracking,
            'sold' => $this->sold,
            'packageType' => $this->packageType,
            'producerInn' => $this->producerInn,
            'grayZone' => $this->grayZone,
            'soldUnitCount' => $this->soldUnitCount,
            'innerUnitCount' => $this->innerUnitCount,
        ];
    }
}
