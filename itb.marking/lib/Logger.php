<?php

namespace Itb\Marking;

use Itb\Core\Logger\FileLogger;

class Logger extends FileLogger
{
    public function __construct()
    {
        $filename = date('Y-m-d') . '.log';
        parent::__construct(__DIR__ . "/../logs/{$filename}");
    }
}
