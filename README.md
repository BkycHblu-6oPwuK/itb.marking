# Интеграция с api честный знак

модуль для bitrix

Минимальная версия php 8.1

# Установка

1. Установить модуль itb.core
2. Установить этот модуль
3. Получить документ подписанный УКЭП
4. Заполнить настройки модуля в Itb\Marking\Options, oauthKey - любой документ подписанный с помощью УКЭП в base64

# Использование

Валидация кодов:

```php
try {
    /** @var \Itb\Marking\Entity\Codes\CodesCheckResult $checkResult */
    $codes = ["mark_code1", "mark_code2"];
    $checkResult = (new \Itb\Marking\Services\CodesCheck())->check($codes);
} catch (\Exception $e) {
    // Вероятнее всего ошибка в запросе - коды не проверены.
}
```

При ошибках логирование идет в ```{dir_module}/logs/``` при включенном логировании в ```Itb\Marking\Options::$logsEnable```

Объект результата проверки ```\Itb\Marking\Entity\Codes\CodesCheckResult```:
- public readonly string $code - Результат обработки операции
- public readonly string $description - Текстовое описание результата выполнения метода
- public readonly \Itb\Marking\Entity\Codes\Code[] $codes - Список КМ
- public readonly string $reqId - Уникальный идентификатор запроса
- public readonly int $reqTimestamp - Дата и время формирования запроса
- public readonly bool $transborderServiceUnavailable - доступность сервиса трансграничной проверки кодов

Объект ```\Itb\Marking\Entity\Codes\Code```:
- public readonly bool $valid - Результат проверки валидности структуры КМ
- И другие поля - подробнее в самом классе

Получение результата проверки из БД:
```php
$codes = ["mark_code1", "mark_code2"];
/** @var null|CodesCheckResult[] сгруппированы по id запросов для каждого кода*/
$checkResult = (new \Itb\Marking\CodeCheckRepository)->findByCisList($codes);
$isValid = $checkResult?->get("mark_code1")?->valid
```

Или через метод ```findByCis``` если не уверены что массив точно совпадает.
```php
/** @var null|\Itb\Marking\Entity\Codes\CodesCheckResult */
$checkResult = (new \Itb\Marking\CodeCheckRepository)->findByCis("mark_code1");
$isValid = $checkResult?->get("mark_code1")?->valid
```

Далее при печате чека, на примере атол, добавить результат проверки в запрос. Валидация либо при обмене и результат берется из БД, либо в этом же классе.

```php
class CashboxAtolFarm extends CashboxAtolFarmV5
{
    /**
     * @var CodesCheckResult[] keyed by CIS
     */
    protected array $checkResults = [];

    public static function getName()
    {
        return 'Атол.Онлайн (ФФД 1.2) - custom';
    }

    public function buildCheckQuery(Check $check)
    {
        $data = $check->getDataForCheck();
        $codes = [];

        foreach ($data['items'] as $item) {
            if (!empty($item['marking_code'])) {
                $codes[] = $item['marking_code'];
            }
        }

        if (!empty($codes)) {
            $results = (new CodeCheckRepository)->findByCisList($codes);

            if (!$results) {
                $results = [(new CodesCheck())->check($codes)];
            }

            foreach ($results as $result) {
                foreach ($result->codes as $cis => $_) {
                    $this->checkResults[$cis] = $result;
                }
            }
        }

        $result = parent::buildCheckQuery($check);

        return $result;
    }

    protected function buildPosition(array $checkData, array $item): array
    {
        $result = parent::buildPosition($checkData, $item);

        if (empty($item['marking_code'])) {
            return $result;
        }

        $cis = $item['marking_code'];
        $checkResult = $this->checkResults[$cis] ?? null;

        if (!$checkResult) {
            throw new Exception("not find code in repository - {$cis}");
        }

        $code = $checkResult->get($cis);

        if (!$code || !$code->valid) {
            throw new Exception("not valid code - {$cis}");
        }

        if (!$checkResult->transborderServiceUnavailable) { // если сервис трансграничной проверки кодов недоступен и результата проверки нет, то продавать товар можно без проверки в режиме онлайн и sectoral_item_props нечем заполнять
            $result['sectoral_item_props'] = $this->buildSectoralItemProps($checkResult);
        }

        return $result;
    }

    protected function buildSectoralItemProps(CodesCheckResult $checkResult): array
    {
        return [
            "federal_id" => '030',
            "date" => '21.11.2023',
            "number" => '1944',
            "value" => "UUID={$checkResult->reqId}&Time={$checkResult->reqTimestamp}",
        ];
    }
}
```