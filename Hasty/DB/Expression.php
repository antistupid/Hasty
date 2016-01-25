<?php

namespace Hasty\DB;

class Expression
{
    /**
     * @todo condition, func parameter bind
     */
    private $name;
    private $alias;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function condition($condition)
    {
        $condexp = [];
        foreach ($condition as $v) {
            $v[0] = is_string($v[0]) ? "'$v[0]'" : $v[0];
            $v[1] = is_string($v[1]) ? "'$v[1]'" : $v[1];
            $condexp[] = 'WHEN ' . $v[0] . ' THEN ' . $v[1];
        }
        $this->name = 'CASE ' . $this->name . ' ' . implode(' ', $condexp) . ' END';
        return $this;
    }

    public function func($function, $arguments = [])
    {
        $argparsed = [];
        foreach ($arguments as $v) {
            if (is_scalar($v))
                $argparsed[] = $v;
            else
                $argparsed[] = $v->getName();
        }
        array_unshift($argparsed, $this->name);
        $argstring = implode(', ', $argparsed);
        $this->name = $function . '(' . $argstring . ')';
        return $this;
    }

    public function alias($alias)
    {
        $this->alias = $alias;
        return $this;
    }

    public function getNameWithAlias()
    {
        if (!$this->alias)
            return $this->name;
        return $this->name . ' AS "' . $this->alias . '"';
    }

    public function getName()
    {
        return $this->name;
    }
}
