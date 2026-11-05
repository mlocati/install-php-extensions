<?php

namespace IPE\Test\Support;

abstract class TestCase4 extends TestCase6
{
    /**
     * @param string $exception
     *
     * @return void
     */
    public function expectException($exception)
    {
        $this->setExpectedException($exception);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public function expectExceptionMessage($message)
    {
        $exception = $this->getExpectedException();
        $this->setExpectedException($exception === null ? 'Exception' : $exception, $message);
    }
}
