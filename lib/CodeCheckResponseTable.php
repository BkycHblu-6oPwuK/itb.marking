<?php

namespace Itb\Marking;

use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\IntegerField;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\Relations\OneToMany;
use Bitrix\Main\Type\DateTime;
use Itb\Core\Traits\TableManagerTrait;

class CodeCheckResponseTable extends DataManager
{
    use TableManagerTrait;

    public static function getTableName(): string
    {
        return 'itb_api_codes_check_response';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),

            new StringField('REQ_ID'),

            new StringField('REQ_TIMESTAMP'),

            new IntegerField('RESPONSE_CODE'),

            new StringField('DESCRIPTION'),

            new BooleanField('TRANSBORDER_SERVICE_UNAVAILABLE', [
                'required' => true,
            ]),

            new DatetimeField('CREATED_AT', [
                'default_value' => function () {
                    return new DateTime();
                }
            ]),

            new OneToMany(
                'CODE',
                CodeCheckTable::class,
                'RESPONSE',
            ),
        ];
    }

    public static function createTable(): void
    {
        if (!static::tableExists()) {
            static::getEntity()->createDbTable();
            $connection = \Bitrix\Main\Application::getConnection();
            $connection->createIndex(
                static::getTableName(),
                'IDX_REQ_ID_TIMESTAMP',
                ['REQ_ID', 'REQ_TIMESTAMP']
            );
        }
    }
}
