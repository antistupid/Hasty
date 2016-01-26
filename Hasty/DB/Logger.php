<?php

namespace Hasty\DB;

use \Hasty\Debugger;

/**
 * Class Logger
 * @package Hasty
 *
 * Query logger for Hasty Debugger
 */
class Logger
{

    private $sql;
    private $params;
    private $types;
    private $startTime;

    /**
     * {@inheritdoc}
     */
    public function startQuery($sql, array $params = null, array $types = null)
    {
        $this->sql = $sql;
        $this->params = $params;
        $this->types = $types;
        $this->startTime = microtime(true);
    }

    /**
     * {@inheritdoc}
     */
    public function stopQuery()
    {
        $elapsed = substr((string)(microtime(true) - $this->startTime), 0, 7);
        Debugger::addQuery($this->sql, $this->params, $this->types, $elapsed);
    }
}

