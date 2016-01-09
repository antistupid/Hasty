<?php

namespace Hasty\Doctrine;

use \Doctrine\DBAL\Logging\SQLLogger;
use \Hasty\Debugger;

/**
 * Class Logger
 * @package Hasty
 *
 * doctrine logger for Hasty Debugger
 */
class Logger implements SQLLogger
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

