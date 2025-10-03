<?php

namespace Itb\Marking;

use Bitrix\Main\Entity\StringField;
use Bitrix\Main\Entity\TextField;
use Bitrix\Main\Entity\DatetimeField;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\Type\DateTime;
use Itb\Core\Traits\TableManagerTrait;

class CodeCheckTable extends DataManager
{
    use TableManagerTrait;

    public static function getTableName(): string
    {
        return 'itb_api_code_check_result';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new IntegerField('RESPONSE_ID', [
                'required' => true,
            ]),

            new StringField('CIS', [
                'required' => true,
                'unique' => true,
            ]),

            new TextField('JSON_RESULT', [
                'required' => true,
            ]),

            new DatetimeField('CREATED_AT', [
                'default_value' => function () {
                    return new DateTime();
                }
            ]),

            new Reference(
                'RESPONSE',
                CodeCheckResponseTable::class,
                Join::on('this.RESPONSE_ID', 'ref.ID')
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
                'IDX_CODE_CHECK_CIS',
                'CIS'
            );
            $connection->createIndex(
                static::getTableName(),
                'IDX_CODE_CHECK_RESPONSE_ID',
                'RESPONSE_ID'
            );
        }
    }
}
