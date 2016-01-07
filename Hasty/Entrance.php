<?php

namespace Hasty;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Entrance
{

    const MAX_SCAN_DEPTH = 10;

    public static function bootstrap($appPath)
    {
        mb_internal_encoding('UTF-8');
        mb_http_output('UTF-8');
        date_default_timezone_set('Asia/Seoul');
        session_start();

        $log = new Logger('slim-skeleton');
        $log->pushHandler(new StreamHandler(ROOT . \DS . 'tmp' . \DS . 'app.log', Logger::DEBUG));

        $routes = [];

        # simple routine from FastRoute tutorial
        $dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector $r) use ($appPath, &$routes) {
            $path = $appPath . \DS . 'Controller';
            self::_require_all($path);

            $controllers = [];
            $controllerName = '\Controller';
            $parentControllerName = '\Hasty\Controller';
            foreach (get_declared_classes() as $name) {
                if ('\\' . substr($name, 0, strlen($controllerName) - 1) == $controllerName
                    and is_subclass_of($name, $parentControllerName)
                )
                    $controllers[] = $name;
            }

            $routes = self::_getClassAnnotations($controllers);
            $ignoredMethods = [];
            foreach ($routes as $c) {
                try {
                    $r->addRoute($c['route']['methods'], $c['route']['path'], $c);
                } catch (\FastRoute\BadRouteException $e) {
                    $ignoredMethods[] = $c;
                    # ignore
                }
            }
        });

        $uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        $ri = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $uri);
        switch ($ri[0]) {
            case Dispatcher::NOT_FOUND:
                header($_SERVER["SERVER_PROTOCOL"] . " 404 Not Found");
                echo '404 not found';
                return;
            case Dispatcher::METHOD_NOT_ALLOWED:
                # $allowedMethods = $ri[1];
                header($_SERVER["SERVER_PROTOCOL"] . " 405 Method Not Allowed");
                echo '405 method not allowed';
                return;
            case Dispatcher::FOUND:
                break;
        }

        # 200 normal request
        list($dummy, $handler, $vars) = $ri;
        $class = new $handler['class']();

        # register request environment
        # end register

        ob_start();
        $return = call_user_func_array(array($class, $handler['method']), $vars);
        $content = ob_get_contents();
        if ($content)
            $log->info(preg_replace("/\\s{2,}/", ' ', $content));
        ob_clean();

        if (is_scalar($return)) {
            echo $return;
        } else if (is_a($return, 'JsonifiedObject')) {
            /** @var $return \JsonifiedObject */
            header('Content-Type: application/json');
            echo $return->getValue();
        } else if (is_a($return, '\Hasty\VO\Render')) {
            $loader = new \Twig_Loader_FileSystem($appPath . \DS . 'View');
            $twig = new \Twig_Environment($loader, array(
                'cache' => '/tmp/compilation_cache',
                'charset' => 'utf-8',
                'auto_reload' => true,
                'strict_variables' => false,
                'autoescape' => true
            ));
            echo $twig->render($return->getTemplate(), $return->getArgs());

            $debugger = new \Hasty\Debugger();
            $debugger->setRouteList($routes);
            $debugger->setRender($return);
            $debugger->loadDebugger();

        } else if (is_a($return, '\Hasty\VO\Redirect')) {
            if ($return->getFlash())
                Session::set('flash', $return->getFlash());
            header('Location: ' . $return->getUrl());
        }
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
                self::_require_all($path, $depth + 1);
        }
    }
}
