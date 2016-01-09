<?php

namespace Hasty;

class Config
{
    private static $config;

    public static function init($config)
    {
        static::$config = $config;
    }

    public static function set($key, $value)
    {
        return static::$config[$key] = $value;
    }

    public static function get($key)
    {
        return static::$config[$key];
    }
}