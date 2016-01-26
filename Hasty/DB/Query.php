<?php

namespace Hasty\DB;

class DBException extends \Exception
{
}

class Query
{

    /** @var \Hasty\DB\Logger */
    public static $logger = null;

    public static function get($entities)
    {
        return new Query($entities);
    }

    private static $connectionString;
    private static $config;

    public static function config($config)
    {
        static::$connectionString = "dbname=$config[dbname] host=$config[host] user=$config[user] password=$config[pass]";
        static::$config = $config;
    }

    private $entities;
    private $conn;

    public function __construct($entities)
    {
        $this->conn = \pg_connect(static::$connectionString);
        if (!$this->conn)
            throw new \Exception('can not connect');
        $this->entities = $entities;
    }

    private $_query = [];

    public static function query($string, $parameters = [])
    {
        $config = static::$config;
        $pdo = new \PDO("pgsql:host=$config[host];dbname=$config[dbname]", $config['user'], $config['pass']);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        try {
            $stmt = $pdo->prepare($string);
            $stmt->setFetchMode(\PDO::FETCH_ASSOC);
            $stmt->execute($parameters);
        } catch (\PDOException $e) {
            throw new DBException($e->getMessage());
        }
        return $stmt->fetchAll();
    }

    public static function eq($a, $b)
    {
        return [$a, new Operator('='), $b];
    }

    public static function gt($a, $b)
    {
        return [$a, new Operator('>'), $b];
    }

    public static function lt($a, $b)
    {
        return [$a, new Operator('<'), $b];
    }

    public static function gte($a, $b)
    {
        return [$a, new Operator('>='), $b];
    }

    public static function lte($a, $b)
    {
        return [$a, new Operator('<='), $b];
    }

    public static function in($a, $b)
    {
        return [$a, new Operator('IN'), $b];
    }

    public static function neq($a, $b)
    {
        return [$a, new Operator('<>'), $b];
    }

    public static function regexpneq($a, $b)
    {
        return [$a, new Operator('!~'), $b];
    }

    public static function eqany($a, $b)
    {
        return [$a, new Operator('= ANY'), $b];
    }

    public static function true($a)
    {
        return [$a, new Operator('true'), null];
    }

    public static function isnull($a)
    {
        return [$a, new Operator('isnull'), null];
    }

    private function makePhrase($conditions)
    {
        $phrases = [];
        foreach ($conditions as $v) {
            /** @var \Hasty\DB\Field $left */
            $left = null;
            /** @var \Hasty\DB\Field $right */
            $right = null;
            /** @var Operator $operator */
            $operator = null;

            list($left, $operator, $right) = $v;

            if ($operator->getOp() == 'isnull') {
                $phrases[] = $left->getName() . ' IS NULL';
                continue;
            }
            if ($operator->getOp() == 'true') {
                $phrases[] = $left->getName();
                continue;
            }
            if ($operator->getOp() == '= ANY') {
                // TODO field명만 됨
                if (!is_scalar($left))
                    $left = $left->getName();
                if (!is_scalar($right))
                    $right = $right->getName();
                $phrases[] = $left . ' = ANY ' . $right;
                continue;
            }
            if ($operator->getOp() == 'IN') {
                // TODO literal array만 됨
                $t = [];
                foreach ($v[2] as $elem) {
                    $t[] = '$' . ($this->bindSequence++);
                    $this->parameters[] = $elem;
                }
                $phrases[] = $left->getName() . ' IN (' . implode(', ', $t) . ')';
                continue;
            }

            $subphrases = [];

            foreach ($v as $v2) {
                if (is_scalar($v2)) {
                    $subphrases[] = '$' . ($this->bindSequence++);
                    $this->parameters[] = $v2;
                } else if (is_a($v2, '\\Hasty\\DB\\Field'))
                    /** @var \Hasty\DB\Field $v2 */
                    $subphrases[] = $v2->getName();
                else if (is_a($v2, '\\Hasty\\DB\\Operator'))
                    /** @var \Hasty\DB\Operator $v2 */
                    $subphrases[] = $v2->getOp();
            }
            $phrases[] = implode(' ', $subphrases);
        }
        return $phrases;
    }

    public function select($fields = [])
    {
        $this->_query = [];
        $this->_query[] = "SELECT";

        if (count($fields) !== 0) {
            $_fields = [];
            foreach ($fields as $v)
                $_fields[] = $v->getName();
        } else {
            $_fields = [];
            foreach ($this->entities as $k => $v) {
                if (is_array($v))
                    $v = $v[0];
                $_fields = array_merge($_fields, $v->getFields());
            }
        }
        $this->_query[] = implode(', ', $_fields);
        $this->_query[] = "FROM";
        $this->_query[] = $this->entities[0]->getTablenameWithAs();

        if (count($this->entities) > 1) {
            for ($i = 1, $count = count($this->entities); $i < $count; $i++) {
                $v = $this->entities[$i];
                if (is_array($v)) {
                    /**
                     * v[0] table
                     * v[1] conditions
                     * v[2] joinMethod <optional>
                     */
                    if (isset($v[2]))
                        $this->_query[] = $v[2];
                    else
                        $this->_query[] = 'INNER JOIN';
                    $this->_query[] = $v[0]->getTablenameWithAs();

                    $this->_query[] = 'ON';
                    $this->_query[] = implode(' AND ', $this->makePhrase($v[1]));
                } else {
                    $this->_query[] = 'NATURAL JOIN';
                    $this->_query[] = $v;
                }
            }
        }

        return $this;
    }

    private $_where = [];
    private $_order = [];
    private $bindSequence = 1;
    private $parameters = [];

    public function where($conditions)
    {
        $this->_where = $this->makePhrase($conditions);
        return $this;
    }

    public function order_by($order)
    {
        foreach ($order as $k => $o)
            if (!is_scalar($o))
                $order[$k] = $o->getName();
        $this->_order = $order;
        return $this;
    }

    private $limit = null;
    private $offset = null;

    public function limit($limit, $offset = null)
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    public function table()
    {
        $resource = $this->_executeQuery();
        /* the first row */
        $result = \pg_fetch_assoc($resource);
        if (!$result)
            return null;
        $keys = array_keys($result);
        $list = [array_values($result)];

        while (($result = \pg_fetch_assoc($resource)))
            $list[] = array_values($result);
        return [
            'keys' => $keys,
            'values' => $list,
        ];
    }

    public function one()
    {
        $resource = $this->_executeQuery();
        $result = \pg_fetch_assoc($resource);
        return $result;
    }

    public function all()
    {
        $resource = $this->_executeQuery();
        $list = [];
        while (($result = \pg_fetch_assoc($resource)))
            $list[] = $result;

        return $list;
    }

    public function remap($key)
    {
        $resource = $this->_executeQuery();
        $list = [];
        while (($result = \pg_fetch_assoc($resource))) {
            if (isset($list[$result[$key]]))
                $list[$result[$key]] = [];
            $list[$result[$key]][] = $result;
        }

        return $list;
    }

    public function hash($key)
    {
        $resource = $this->_executeQuery();
        $list = [];
        while (($result = \pg_fetch_assoc($resource)))
            $list[$result[$key]] = $result;
        return $list;
    }

    public function walk($callback)
    {
        $resource = $this->_executeQuery();
        while (($result = \pg_fetch_assoc($resource)))
            $callback($result);
    }

    private function _executeQuery()
    {
        $rawQuery = implode(' ', $this->_query);
        if ($this->_where)
            $rawQuery .= ' WHERE ' . implode(' AND ', $this->_where);
        if ($this->_order)
            $rawQuery .= ' ORDER BY ' . implode(', ', $this->_order);
        if ($this->limit)
            $rawQuery .= ' LIMIT ' . $this->limit;
        if ($this->offset)
            $rawQuery .= ' OFFSET ' . $this->offset;

        $queryName = 'query' . microtime();
        if (static::$logger)
            static::$logger->startQuery($rawQuery, $this->parameters);
        \pg_prepare($this->conn, $queryName, $rawQuery);
        $resource = \pg_execute($this->conn, $queryName, $this->parameters);
        if (static::$logger)
            static::$logger->stopQuery();
        if (!$resource)
            throw new \Exception(\pg_errormessage($this->conn));
        return $resource;
    }

    public function __toString()
    {
        return implode(' ', $this->_query);
    }
}

class Operator
{
    private $op;

    public function __construct($op)
    {
        $this->op = $op;
    }

    public function getOp()
    {
        return $this->op;
    }

}
