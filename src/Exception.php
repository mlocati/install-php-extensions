<?php

namespace IPE;

abstract class Exception extends \RuntimeException
{
    /**
     * @param string $message
     */
    public function __construct($message)
    {
        parent::__construct($message);
    }

    /**
     * @return \IPE\Exception\UserMessage
     */
    public function asUserMessage()
    {
        if ($this instanceof Exception\UserMessage) {
            return $this;
        }

        return new Exception\UserMessage($this->getMessage());
    }
}
