<?php

namespace IPE\Traits;

use IPE\Exception;
use IPE\OS;

/**
 * Implements \IPE\OS::getMachineArchitecture() and \IPE\OS::getRawMachineArchitecture().
 */
trait MachineArchitecture
{
    /**
     * @var string|null
     */
    private $machineArchitecture;

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getMachineArchitecture()
     */
    public function getMachineArchitecture()
    {
        return OS\Architecture::fromName($this->getRawMachineArchitecture());
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getRawMachineArchitecture()
     */
    public function getRawMachineArchitecture()
    {
        if ($this->machineArchitecture === null) {
            $this->machineArchitecture = $this->detectMachineArchitecture();
        }
        if ($this->machineArchitecture === '') {
            throw new Exception\IO('Failed to detect the architecture of the machine');
        }

        return $this->machineArchitecture;
    }

    /**
     * @return string empty string if it can't be detected
     */
    abstract protected function detectMachineArchitecture();
}
