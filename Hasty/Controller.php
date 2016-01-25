<?php

namespace Hasty;

use Hasty\VO\Jsonified;
use Hasty\VO\Redirect;
use Hasty\VO\Render;

class Controller
{
    final protected function render($template, $args = [])
    {
        $args['SERVER'] = $_SERVER;

        if (Session::get('flash')) {
            $args['flash'] = Session::get('flash');
            Session::del('flash');
        }
        return new Render($template, $args);
    }

    final protected function jsonify($array, $httpResponseCode = 200)
    {
        return new Jsonified($array, $httpResponseCode);
    }

    final protected function redirect($url, $flash = '')
    {
        return new Redirect($url, $flash);
    }

    final protected function url_for($url)
    {
        throw new \Exception('not implemented.');
    }

    final protected function isPost()
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}