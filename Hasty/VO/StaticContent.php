<?php

namespace Hasty\VO;

class StaticContent
{
    private $value;
    private $contentType;

    public function __construct($value, $contentType)
    {
        $this->value = $value;
        $this->contentType = $contentType;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getContentType()
    {
        return $this->contentType;
    }
}

