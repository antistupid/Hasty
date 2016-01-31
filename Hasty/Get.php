<?php

namespace Hasty;

class Get
{
    private static $parameters = [];

    public static function buildQuery($override = [])
    {
        if ($override)
            return '?' . \http_build_query(array_merge(static::$parameters, $override));
        return '?' . \http_build_query(static::$parameters);
    }

    public static function get($key)
    {
        if (empty(static::$parameters[$key]))
            return null;
        return static::$parameters[$key];
    }

    public static function all()
    {
        return static::$parameters;
    }

    public static function install()
    {
        static::$parameters = $_GET;
    }
}
