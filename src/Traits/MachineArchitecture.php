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
        $raw = $this->getRawMachineArchitecture();
        if (preg_match('/^(i[3-6]86|x86)$/Di', $raw)) {
            return OS\Architecture::X86;
        }
        if (preg_match('/^(x86_64|amd64)$/Di', $raw)) {
            return OS\Architecture::X64;
        }
        if (preg_match('/^(aarch64|arm64|armv8)$/Di', $raw)) {
            return OS\Architecture::ARM64;
        }

        // armv8l is an ARMv8 kernel running in 32-bit mode
        return preg_match('/^arm(v[5-8].*)?$/Di', $raw) ? OS\Architecture::ARM32 : OS\Architecture::OTHER;
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
