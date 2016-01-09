<?php

namespace Hasty;

use Monolog\Logger as MonologLogger;

class Logger
{

    private static $logger;

    public static function init($name, $handler)
    {
        static::$logger = new MonologLogger($name);
        static::$logger->pushHandler($handler);
    }

    public static function __callStatic($name, $arguments)
    {
        static::$logger->{$name}($arguments);
    }
}