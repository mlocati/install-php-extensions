<?php

namespace IPE\Test\Support\SystemDependencyTest;

use IPE\Option\CustomOption;
use IPE\SystemDependency;

/**
 * A dependency that's not among the default ones, with its own options.
 */
final class FakeThirdPartyLibrary extends SystemDependency
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getHandle()
     */
    public static function getHandle()
    {
        return 'lib-third-party';
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getOptions()
     */
    public static function getOptions()
    {
        return [
            new CustomOption('edition', '', true, 'free'),
        ];
    }

    /**
     * @return string|true|null
     */
    public function getEdition()
    {
        return $this->options->getCustomValue('edition');
    }
}
