<?php

namespace Hasty;

use FastRoute\BadRouteException;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

class App
{

    const MAX_SCAN_DEPTH = 10;

    public static function run($appPath)
    {
        $routeInfo = static::getRoutes($appPath);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                return '404 not found';
            case Dispatcher::METHOD_NOT_ALLOWED:
                # $allowedMethods = $ri[1];
                header($_SERVER["SERVER_PROTOCOL"] . " 405 Method Not Allowed");
                return '405 method not allowed';
            case Dispatcher::FOUND:
                break;
        }

        list(, $handler, $vars) = $routeInfo;
        $class = new $handler['class']();
        $method = $handler['method'];

        ob_start();
        $return = call_user_func_array(array($class, $method), $vars);
        $content = ob_get_contents();
        ob_clean();
        if ($content)
            foreach (explode(\BUFFER_SEP, preg_replace("/\\s{2,}|\\n/", ' ',
                trim($content, \BUFFER_SEP))) as $v)
                \Hasty\Logger::info($v);

        switch (true) {
            case is_scalar($return):
                return $return;
            case is_a($return, 'JsonifiedObject'):
                /** @var $return \JsonifiedObject */
                header('Content-Type: application/json');
                return $return->getValue();
            case is_a($return, '\Hasty\VO\Redirect'):
                if ($return->getFlash())
                    Session::set('flash', $return->getFlash());
                header('Location: ' . $return->getUrl());
                return;
            case is_a($return, '\Hasty\VO\Render'):
                break;
        }

        $loader = new \Twig_Loader_FileSystem($appPath . \DS . 'View');
        $twig = new \Twig_Environment($loader, array(
            'cache' => '/tmp/compilation_cache',
            'charset' => 'utf-8',
            'auto_reload' => true,
            'strict_variables' => false,
            'autoescape' => true
        ));

        if (Config::get('debug')) Debugger::setRender($return);

        return $twig->render($return->getTemplate(), $return->getArgs());
    }

    private static function _getClassAnnotations($classes)
    {
        $array = [];
        foreach ($classes as $class) {
            $r = new \ReflectionClass($class);
            foreach ($r->getMethods() as $m) {
                $doc = $m->getDocComment();
                preg_match_all('#@route\s(.*?)\n#s', $doc, $annotations);
                foreach ($annotations[1] as $a) {
                    $sp = explode(' /', $a);
                    $methods = array_map('trim', explode(',', $sp[0]));
                    $array[] = [
                        'method' => $m->getName(),
                        'class' => $r->getName(),
                        'route' => [
                            'methods' => $methods,
                            'path' => '/' . trim($sp[1])
                        ]
                    ];
                }
            }
        }
        return $array;
    }

    private static function _require_all($dir, $depth = 0)
    {
        if ($depth > self::MAX_SCAN_DEPTH)
            return;
        $scan = glob($dir . \DS . '*');
        foreach ($scan as $path) {
            if (preg_match('/\.php$/', $path))
                require_once($path);
            elseif (is_dir($path))
                static::_require_all($path, $depth + 1);
        }
    }

    private static function getRoutes($appPath)
    {
        $routes = [];
        # simple routine from FastRoute tutorial
        $dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector $r) use ($appPath, &$routes) {
            $path = $appPath . \DS . 'Controller';
            static::_require_all($path);

            $controllers = [];
            $controllerName = '\Controller';
            $parentControllerName = '\Hasty\Controller';
            foreach (get_declared_classes() as $name) {
                if ('\\' . substr($name, 0, strlen($controllerName) - 1) == $controllerName
                    and is_subclass_of($name, $parentControllerName)
                )
                    $controllers[] = $name;
            }

            $routes = static::_getClassAnnotations($controllers);
            $ignoredMethods = [];
            foreach ($routes as $c) {
                try {
                    $r->addRoute($c['route']['methods'], $c['route']['path'], $c);
                } catch (BadRouteException $e) {
                    $ignoredMethods[] = $c;
                    # ignore
                }
            }
        });

        if (Config::get('debug')) Debugger::setRouteList($routes);

        $ri = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'],
            rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));

        return $ri;
    }
}
