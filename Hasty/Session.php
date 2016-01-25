<?php

namespace Hasty;

class Session
{
    public static function get($key)
    {
        if (empty($_SESSION[$key]))
            return null;
        return $_SESSION[$key];
    }

    public static function all()
    {
        return $_SESSION;
    }

    public static function del($key)
    {
        if (empty($_SESSION[$key]))
            return null;
        unset($_SESSION[$key]);
    }

    public static function set($key, $value = '')
    {
        if (!is_array($key))
            return $_SESSION[$key] = $value;
        foreach ($key as $k => $v)
            $_SESSION[$k] = $v;
    }
}
