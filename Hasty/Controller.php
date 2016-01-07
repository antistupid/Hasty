<?php

namespace Hasty;

use Hasty\VO\Render;
use Hasty\VO\Redirect;

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