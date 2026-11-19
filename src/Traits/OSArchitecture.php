<?php

namespace IPE\Traits;

use IPE\Architecture;
use IPE\Exception;

/**
 * Implements \IPE\OS::getArchitecture().
 */
trait OSArchitecture
{
    /**
     * @var \IPE\Architecture|null
     */
    private $architecture;

    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS::getArchitecture()
     */
    public function getArchitecture()
    {
        if ($this->architecture === null) {
            $rawName = $this->detectRawArchitecture();
            if ($rawName === '') {
                throw new Exception\IO('Failed to detect the architecture of the machine');
            }
            $this->architecture = new Architecture($rawName);
        }

        return $this->architecture;
    }

    /**
     * @return string empty string if it can't be detected
     */
    abstract protected function detectRawArchitecture();
}
