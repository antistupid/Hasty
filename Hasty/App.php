<?php

namespace {

    use Hasty\Logger;

    function dump($obj)
    {
        print_r($obj);
        if (php_sapi_name() != 'cli')
            echo \BUFFER_SEP;
    }

    class UnregisterableCallback
    {

        /** @var UnregisterableCallback */
        public static $handler;

        public static function setHandler($callback)
        {
            static::$handler = new \UnregisterableCallback($callback);
            register_shutdown_function(array(static::$handler, 'call'));
        }

        // Store the Callback for Later
        private $callback;

        // Check if the argument is callable, if so store it
        public function __construct($callback)
        {
            if (is_callable($callback)) {
                $this->callback = $callback;
            } else {
                throw new InvalidArgumentException("Not a Callback");
            }
        }

        // Check if the argument has been unregistered, if not call it
        public function call()
        {
            if ($this->callback == false)
                return false;

            $callback = $this->callback;
            $callback(); // weird PHP bug
        }

        // Unregister the callback
        public function unregister()
        {
            $this->callback = false;
        }
    }

    \UnregisterableCallback::setHandler(function () {
        $content = ob_get_contents();
        ob_clean();
        if ($content) {
            foreach (explode(\BUFFER_SEP,
                trim($content, \BUFFER_SEP)) as $v) {
                if (trim($v))
                    Logger::info($v);
            }
        }
        header($_SERVER["SERVER_PROTOCOL"] . " 500");
    });
}

namespace Hasty {

    use FastRoute\BadRouteException;
    use FastRoute\Dispatcher;
    use FastRoute\RouteCollector;
    use Hasty\DB\Logger as DBLogger;
    use Hasty\DB\Query;
    use Monolog\Handler\StreamHandler;
    use Monolog\Logger as MonologLogger;

    class App
    {

        const MAX_SCAN_DEPTH = 10;

        private static function route($appPath)
        {
            $routeInfo = static::_getRoutes($appPath);

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
                    Logger::info($v);

            \UnregisterableCallback::$handler->unregister();

            switch (true) {
                case is_scalar($return):
                    return $return;
                case is_a($return, '\\Hasty\\VO\\StaticContent'):
                    exit;
                case is_a($return, '\\Hasty\\VO\\Jsonified'):
                    /** @var $return \Hasty\VO\Jsonified */
                    header('Content-Type: application/json');
                    if ($return->getStatus() !== 200)
                        header($_SERVER["SERVER_PROTOCOL"] . " " . $return->getStatus());
                    return $return->getValue();
                case is_a($return, '\\Hasty\\VO\\Redirect'):
                    if ($return->getFlash())
                        Session::set('flash', $return->getFlash());
                    header('Location: ' . $return->getUrl());
                    return;
                case is_a($return, '\Hasty\VO\Render'):
                    break;
            }

            $loader = new \Twig_Loader_Filesystem($appPath . \DS . 'View');
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

        private static function _getRoutes($appPath)
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

        public static function run($appPath)
        {
            define('DS', DIRECTORY_SEPARATOR);
            define('ROOT', dirname(realpath($appPath)));

            /* constants */
            define('BUFFER_SEP', "\xAA");
            define('NL', "\n");

            /* config */
            // $c = \explode(':', getenv('CONFIG'));
            $c = \explode(':', 'config.php:development');
            if (count($c) != 2)
                die('specify CONFIG_FILE');
            $configFile = $c[0];
            $configSection = $c[1];
            if (!$configFile || !file_exists(ROOT . \DS . $configFile))
                die('invalid config File');
            $config = require_once(ROOT . \DS . $configFile);
            if (!isset($config[$configSection]))
                die('invalid config section');
            Config::init($config[$configSection]);

            /* ini set */
            ini_set('display_errors', 1);
            ini_set('html_errors', 0);
            ini_set('error_log', 'syslog');
            error_reporting(E_ALL);

            mb_internal_encoding('UTF-8');
            mb_http_output('UTF-8');
            date_default_timezone_set('Asia/Seoul');
            session_start();

            /* install static classes */
            Get::install();

            /* log */
            Logger::init(Config::get('name'),
                new StreamHandler(ROOT . \DS . 'tmp' . \DS . 'app.log', MonologLogger::DEBUG));

            /* DB logger */
            if (Config::get('debug'))
                Query::$logger = new DBLogger();

            echo static::route($appPath);

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                exit(0);
            }

            if (Config::get('debug'))
                Debugger::loadDebugger();
        }
    }
}

