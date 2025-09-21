<?php

namespace Itb\Marking;

use Itb\Core\Modules\Options\AbstractOptions;

final class Options extends AbstractOptions
{
    /** url для авторизации и получения cdn */
    public readonly string $baseUrl;
    /** любой документ подписанный с помощью УКЭП в base64 */
    public readonly string $oauthKey;
    /** токен полученный через лк, если oauthKey пустой, то используется этот токен */
    public readonly string $token;
    public readonly string $defaultFiscalDriveNumber;
    public readonly bool $isTest;
    public readonly bool $logsEnable;

    protected function mapOptions(array $options): void
    {
        $this->oauthKey = $options['oauth_key'] ?? '';
        $this->token = $options['token'] ?? '';
        $this->defaultFiscalDriveNumber = $options['default_fdn'] ?? '';
        $this->isTest = ($options['is_test'] ?? 'N') === 'Y';
        $this->logsEnable = ($options['logs_enable'] ?? 'Y') === 'Y';

        $this->baseUrl = $this->isTest
            ? 'https://markirovka.sandbox.crptech.ru'
            : 'https://cdn.crpt.ru';
    }

    public function getModuleId(): string
    {
        return 'itb.marking';
    }
}
