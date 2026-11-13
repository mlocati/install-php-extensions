<?php

namespace IPE\SystemPackager\DNF;

use IPE\Exception;
use IPE\Filesystem\Command;
use IPE\SystemPackager\DNF;

/**
 * dnf4: Enterprise Linux 8 to 10, Amazon Linux 2023.
 */
final class v4 extends DNF
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager\DNF::getQueryFormat()
     */
    protected function getQueryFormat()
    {
        // dnf4 adds a new line after every package
        return '%{name}|%{epoch}|%{version}|%{release}|%{arch}|%{sourcerpm}';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager\DNF::getSkipUnavailableOption()
     */
    protected function getSkipUnavailableOption()
    {
        return '--setopt=strict=False';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemPackager::doMarkAsManuallyInstalled()
     */
    protected function doMarkAsManuallyInstalled(array $packages)
    {
        list($rc) = $this->os->getFilesystem()->run($this->createDnfCommand(array_merge(['mark', 'install'], $packages))->withOutput(Command::OUTPUT_SHOW));
        if ($rc !== 0) {
            throw new Exception\SystemPackager('dnf mark install failed');
        }
    }
}
