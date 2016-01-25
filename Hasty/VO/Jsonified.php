<?php

namespace Hasty\VO;

class Jsonified
{
    private $value;
    private $status;

    public function __construct($value, $httpResponseCode)
    {
        $this->value = json_encode($value, JSON_PRETTY_PRINT);
        $this->status = $httpResponseCode;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getStatus()
    {
        return $this->status;
    }
}

