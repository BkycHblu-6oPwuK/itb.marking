<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Loader;
use Itb\Marking\CodeCheckResponseTable;
use Itb\Marking\CodeCheckTable;

Loc::loadMessages(__FILE__);

class itb_marking extends CModule
{

    public function __construct()
    {
        if (is_file(__DIR__ . '/version.php')) {
            include_once(__DIR__ . '/version.php');
            $this->MODULE_ID           = 'itb.marking';
            $this->MODULE_VERSION      = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
            $this->MODULE_NAME         = Loc::getMessage('ITB_MARKING_NAME');
            $this->MODULE_DESCRIPTION  = Loc::getMessage('ITB_MARKING_DESCRIPTION');
            $this->PARTNER_NAME = 'Itb';
            $this->PARTNER_URI = '#';
        } else {
            CAdminMessage::showMessage(
                Loc::getMessage('ITB_MARKING_FILE_NOT_FOUND') . ' version.php'
            );
        }
    }

    public function DoInstall()
    {
        global $APPLICATION;
        if ($this->isVersionD7()) {
            ModuleManager::registerModule($this->MODULE_ID);
            Loader::includeModule($this->MODULE_ID);
            $this->InstallDB();
        } else {
            $APPLICATION->ThrowException('Нет поддержки d7 в главном модуле');
        }
        $APPLICATION->IncludeAdminFile(
            'Установка модуля',
            __DIR__ . '/step.php'
        );
    }

    protected function isVersionD7()
    {
        return CheckVersion(ModuleManager::getVersion('main'), '14.0.0');
    }

    public function InstallDB()
    {
        CodeCheckTable::createTable();
        CodeCheckResponseTable::createTable();
    }

    public function UnInstallDB()
    {
        CodeCheckTable::dropTable();
        CodeCheckResponseTable::dropTable();
    }

    public function doUninstall()
    {
        global $APPLICATION;

        $context = \Bitrix\Main\Context::getCurrent();
        $request = $context->getRequest();
        Loader::includeModule($this->MODULE_ID);
        if ($request['step'] < 2) {
            $APPLICATION->IncludeAdminFile(Loc::getMessage('ITB_FAVORITE_UNINSTALL_TITLE'), __DIR__ . '/unstep1.php');
        } else {
            if ($request['savedata'] !== 'Y') {
                $this->UnInstallDB();
            }

            \Bitrix\Main\ModuleManager::unRegisterModule($this->MODULE_ID);

            $APPLICATION->IncludeAdminFile(Loc::getMessage('ITB_FAVORITE_UNISTALL_TITLE'), __DIR__ . '/unstep2.php');
        }
    }
}
