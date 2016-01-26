<?php

/**
 * @reference flask debugtoolbar
 */

namespace Hasty;

class Debugger
{
    private static $queries = [];
    private static $routeList;
    private static $render;

    public static function setRouteList($routeList)
    {
        static::$routeList = $routeList;
    }

    public static function setRender($render)
    {
        static::$render = $render;
    }

    private static function _generateTable($data)
    {
        $t = '<table><thead><tr>';
        $header = array_shift($data);
        foreach ($header as $v)
            $t .= '<th>' . $v . '</th>';
        $t .= '</tr></thead>';
        $flip = false;
        $t .= '<tbody>';
        foreach ($data as $row) {
            $t .= '<tr class="' . (($flip = !$flip) ? 'flDebugOdd' : 'flDebugEven') . '">';
            foreach ($row as $cell)
                $t .= '<td>' . $cell . '</td>';
            $t .= '</tr>';
        }
        $t .= '</tbody></table>';
        return $t;
    }

    public static function loadDebugger()
    {
        # server
        $raw = [['key', 'value']];
        foreach ($_SERVER as $k => $v)
            $raw[] = [$k, $v];
        $data['server'] = static::_generateTable($raw);

        # session
        $raw = [['key', 'value']];
        foreach ($_SESSION as $k => $v)
            $raw[] = [$k, '<pre>' . json_encode($v, JSON_PRETTY_PRINT) . '</pre>'];
        $data['session'] = static::_generateTable($raw);

        # cookie
        $raw = [['key', 'value']];
        foreach ($_COOKIE as $k => $v)
            $raw[] = [$k, $v];
        $data['cookie'] = static::_generateTable($raw);

        # template
        $raw = [['url', 'endpoint', 'methods']];
        foreach (static::$routeList as $v)
            $raw[] = [
                $v['route']['path'],
                $v['class'] . '::' . $v['method'],
                implode(', ', $v['route']['methods'])
            ];
        $data['route'] = static::_generateTable($raw);

        # template
        $raw = [['key', 'value']];
        if (static::$render) {
            foreach (static::$render->getArgs() as $k => $v)
                $raw[] = [$k, '<pre>' . json_encode($v, JSON_PRETTY_PRINT) . '</pre>'];
            $data['template'] = '<h3>' . static::$render->getTemplate() . '</h3>'
                . static::_generateTable($raw);
        }

        # queries
        $raw = [['sql', 'params', 'types', 'ellapsed']];
        # array_unshift(static::$queries, ['sql', 'params', 'types', 'ellapsed']);
        foreach ((array) static::$queries as $v)
            $raw[] = [$v[0], json_encode($v[1]),
                json_encode($v[2]),
                $v[3]];
        $data['queries'] = static::_generateTable($raw);

        $panels = [];
        foreach ($data as $k => $v) {
            $panels[] = [
                'dom_id' => $k . 'Panel',
                'has_content' => true,
                'title' => $k,
                'nav_title' => $k,
                'content' => $v,
            ];
        }

        $debugValue = ['panels' => $panels];

        $loader = new \Twig_Loader_FileSystem(__DIR__ . \DS . 'Debugger');
        $twig = new \Twig_Environment($loader, array(
            'cache' => '/tmp/compilation_cache',
            'charset' => 'utf-8',
            'auto_reload' => true,
            'strict_variables' => false,
            'autoescape' => true
        ));
        echo $twig->render('template.twig', $debugValue);
    }

    public static function addQuery($sql, $params, $types, $ellapsed)
    {
        static::$queries[] = [$sql, $params, $types, $ellapsed];
    }
}

