<?php

namespace Itb\Marking;

final class Options
{
    const MODULE_ID = 'itb.marking';

    private static $instance;

    /** url для авторизации и получения cdn */
    public readonly string $baseUrl;
    /** любой документ подписанный с помощью УКЭП в base64 */
    public readonly string $oauthKey;
    /** токен полученный через лк, если oauthKey пустой, то используется этот токен */
    public readonly string $token;
    public readonly string $defaultFiscalDriveNumber;
    public readonly bool $isTest;
    public readonly bool $logsEnable;

    private function __construct()
    {
        $this->oauthKey = '';
        $this->token = '';
        $this->defaultFiscalDriveNumber = '';
        $this->isTest = false;
        $this->logsEnable = true;
        $this->baseUrl = $this->isTest ? 'https://markirovka.sandbox.crptech.ru' : 'https://cdn.crpt.ru';
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }
}
