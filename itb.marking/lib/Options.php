<?php

namespace Itb\Marking;

final class Options
{
    const MODULE_ID = 'itb.marking';

    private static $instance;

    /**
     * любой документ подписанный с помощью УКЭП в base64
     */
    public readonly string $oauthKey;
    public readonly bool $isTest;
    public readonly bool $logsEnable;

    private function __construct()
    {
        //$options = \Bitrix\Main\Config\Option::getForModule(self::MODULE_ID);
        $this->oauthKey = 'key';
        $this->isTest = true;
        $this->logsEnable = true;
    }

    public static function getInstance()
    {
        if(self::$instance === null){
            self::$instance = new self;
        }
        return self::$instance;
    }
}
