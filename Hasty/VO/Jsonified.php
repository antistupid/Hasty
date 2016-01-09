<?php

namespace Hasty\VO;

class JsonifiedObject
{
    public $value;

    public function __construct($value)
    {
        $this->value = json_encode($value, JSON_PRETTY_PRINT);
    }

    public function getValue()
    {
        return $this->value;
    }
}
