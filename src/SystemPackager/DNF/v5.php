<?php

namespace IPE\SystemPackager\DNF;

use IPE\Exception;
use IPE\Filesystem\Command;
use IPE\SystemPackager\DNF;

/**
 * dnf5: Fedora 41 and later.
 */
final class v5 extends DNF
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager\DNF::getQueryFormat()
     */
    protected function getQueryFormat()
    {
        return '%{name}|%{epoch}|%{version}|%{release}|%{arch}|%{sourcerpm}\n';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager\DNF::getSkipUnavailableOption()
     */
    protected function getSkipUnavailableOption()
    {
        return '--skip-unavailable';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doMarkAsManuallyInstalled()
     */
    protected function doMarkAsManuallyInstalled(array $packages)
    {
        // Unlike dnf4, it asks for confirmation
        list($rc) = $this->os->getFilesystem()->run($this->createDnfCommand(array_merge(['mark', 'user', '-y'], $packages))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('dnf mark user failed');
        }
    }
}
