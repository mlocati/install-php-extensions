<?php

namespace IPE\Traits;

use IPE\Filesystem\Command;
use IPE\Filesystem\VolatileDirectory;

trait Filesystem
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\Filesystem::createVolatileDirectory()
     */
    public function createVolatileDirectory()
    {
        return new VolatileDirectory($this->createTemporaryDirectory(), $this);
    }

    /**
     * Prepare the command line to be executed (raw commands are returned as is).
     *
     * @throws \IPE\Exception\InvalidArgument
     *
     * @return non-empty-string
     */
    protected function prepareFullCommandLine(Command $command)
    {
        if ($command->isRaw()) {
            return $command->getCommand();
        }
        $chunks = array_merge([$command->getCommand()], $command->getArguments());
        $chunks = array_map([$this, 'escapeShellArg'], $chunks);

        return implode(' ', $chunks);
    }
}
