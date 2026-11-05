<?php

namespace IPE\Extension\Asked\Resolver;

use IPE\Exception;
use IPE\Extension\Asked;
use IPE\Extension\Asked\Resolver;
use IPE\Installer\State;

class Windows extends Resolver
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\Asked\Resolver::resolveAskedExtensionFromDirectory()
     */
    protected function resolveAskedExtensionFromDirectory(Asked\FromDirectory $asked, State $state, $origin)
    {
        if ($this->options->getOnUnsupportedExtensions() === Options::ONUNSUPPORTED_SKIP) {
            $this->logger->warning("Compiling extensions from a directory ({$asked->getPath()}) is not supported on Windows", ['path' => $asked->getPath()]);

            return null;
        }
        throw new Exception\UnsupportedExtension("You asked to compile the extension from the directory\n{$asked->getPath()}\nbut compiling extensions is not supported on Windows");
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\Extension\Asked\Resolver::resolveAskedExtensionFromURL()
     */
    protected function resolveAskedExtensionFromURL(Asked\FromURL $asked, State $state)
    {
        if ($this->options->getOnUnsupportedExtensions() === Options::ONUNSUPPORTED_SKIP) {
            $this->logger->warning("Compiling extensions from a URL ({$asked->getURL()}) is not supported on Windows", ['url' => $asked->getURL()]);

            return null;
        }
        throw new Exception\UnsupportedExtension("You asked to compile the extension from the URL\n{$asked->getURL()}\nbut compiling extensions is not supported on Windows");
    }
}
