<?php

namespace Hasty\DB;

class Field
{

    private $class;
    private $name;

    public function __construct($class, $name)
    {
        $this->class = $class;
        $this->name = $class . '.' . $name;
    }

    public function condition($condition)
    {
        return $this->newExp()->condition($condition);
    }

    public function func($function, $arguments = [])
    {
        return $this->newExp()->func($function, $arguments);
    }

    public function alias($alias)
    {
        return $this->newExp()->alias($alias);
    }

    public function getNameWithAlias()
    {
        return $this->newExp()->getNameWithAlias();
    }

    private function newExp()
    {
        return new Expression($this->name);
    }

    public function getName()
    {
        return $this->name;
    }

}

