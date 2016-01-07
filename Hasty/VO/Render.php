<?php

namespace Hasty\VO;

final class Render
{
    /**
     * @var $template string
     */
    private $template;
    /**
     * @var $args array
     */
    private $args;

    /**
     * Render constructor.
     * @param $template string
     * @param $args array
     */
    public function __construct($template, $args)
    {
        $this->template = $template;
        $this->args = $args;
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @return array
     */
    public function getArgs()
    {
        return $this->args;
    }


}
