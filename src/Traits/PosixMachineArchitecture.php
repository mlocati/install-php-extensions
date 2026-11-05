<?php

namespace IPE\Traits;

use IPE\Filesystem\Command;

trait PosixMachineArchitecture
{
    use MachineArchitecture;

    /**
     * @return \IPE\Filesystem
     */
    abstract public function getFilesystem();

    /**
     * @throws \IPE\Exception\IO
     *
     * @return string empty string if it can't be detected
     */
    protected function detectMachineArchitecture()
    {
        list($rc, $output) = $this->getFilesystem()->run(Command::create('uname', ['-m'])->withOutput(Command::OUTPUT_CAPTURE_STDOUT));

        return $rc === 0 ? trim($output) : '';
    }
}
