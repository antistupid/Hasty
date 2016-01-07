<?php

namespace Hasty\VO;

class Redirect
{
    private $url;
    private $flash;

    public function __construct($url, $flash)
    {
        $this->url = $url;
        if (is_string($flash))
            $flash = ['info' => $flash];
        $this->flash = $flash;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return mixed
     */
    public function getFlash()
    {
        return $this->flash;
    }
}
