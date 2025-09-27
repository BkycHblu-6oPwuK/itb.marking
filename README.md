# itb.marking
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
- public readonly bool $verified - Результат проверки валидности структуры КМ
- И другие поля - подробнее в самом классе

Получение результата проверки из БД:
```php
$codes = ["mark_code1", "mark_code2"];
/** @var null|CodesCheckResult[] сгруппированы по id запросов для каждого кода*/
$checkResult = (new \Itb\Marking\CodeCheckRepository)->findByCisList($codes);
$isValid = $checkResult?->get("mark_code1")?->verified
```

Или через метод ```findByCis```
```php
/** @var null|\Itb\Marking\Entity\Codes\CodesCheckResult */
$checkResult = (new \Itb\Marking\CodeCheckRepository)->findByCis("mark_code1");
$isValid = $checkResult?->get("mark_code1")?->verified
```

Далее при печате чека, на примере атол, добавить результат проверки в запрос. Создаете свой обработчик кассы и используйте trait ``` Itb\Marking\Traits\MarkingCashboxTrait ```
