<?php

/**
 * @reference flask debugtoolbar
 */

namespace Hasty;

class Debugger
{
    private $routeList;
    private $render;

    public function setRouteList($routeList)
    {
        $this->routeList = $routeList;
    }

    public function setRender($render)
    {
        $this->render = $render;
    }

    private function _generateTable($data)
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

    public function loadDebugger()
    {
        # server
        $raw = [['key', 'value']];
        foreach ($_SERVER as $k => $v)
            $raw[] = [$k, $v];
        $data['server'] = $this->_generateTable($raw);

        # session
        $raw = [['key', 'value']];
        foreach ($_SESSION as $k => $v)
            $raw[] = [$k, '<pre>' . json_encode($v, JSON_PRETTY_PRINT) . '</pre>'];
        $data['session'] = $this->_generateTable($raw);

        # cookie
        $raw = [['key', 'value']];
        foreach ($_COOKIE as $k => $v)
            $raw[] = [$k, $v];
        $data['cookie'] = $this->_generateTable($raw);

        # template
        $raw = [['url', 'endpoint', 'methods']];
        foreach ($this->routeList as $v)
            $raw[] = [
                $v['route']['path'],
                $v['class'] . '::' . $v['method'],
                implode(', ', $v['route']['methods'])
            ];
        $data['route'] = $this->_generateTable($raw);

        # template
        $raw = [['key', 'value']];
        foreach ($this->render->getArgs() as $k => $v)
            $raw[] = [$k, '<pre>' . json_encode($v, JSON_PRETTY_PRINT) . '</pre>'];
        $data['template'] = '<h3>' . $this->render->getTemplate() . '</h3>'
            . $this->_generateTable($raw);

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
}

