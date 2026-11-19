<?php

namespace IPE\Traits\OSArchitecture;

use IPE\Filesystem\Command;
use IPE\Traits\OSArchitecture;

trait Posix
{
    use OSArchitecture;

    /**
     * @return \IPE\Filesystem
     */
    abstract public function getFilesystem();

    /**
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if it can't be detected
     */
    protected function detectRawArchitecture()
    {
        list($rc, $output) = $this->getFilesystem()->run(Command::create('uname', ['-m'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));

        return $rc === 0 ? trim($output) : '';
    }
}
