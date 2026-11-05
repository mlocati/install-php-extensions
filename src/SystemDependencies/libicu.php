<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\Option\CustomOption;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

class libicu extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-icu';

    /**
     * @public
     * @var string
     */
    const OPTION_EN_ONLY = 'en-only';

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getOptions()
     */
    public static function getOptions()
    {
        return [
            new CustomOption(self::OPTION_EN_ONLY, 'Install a smaller, English-only, ICU library on Alpine 3.16 and later', false),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getHandle()
     */
    public static function getHandle()
    {
        return self::HANDLE;
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        if ($this->os instanceof OS\Alpine) {
            if ($this->hasSplitData($this->os) && !$this->options->hasCustomValue(self::OPTION_EN_ONLY)) {
                return new Packages(
                    ['icu-libs', 'icu-data-full'],
                    ['icu-dev']
                );
            }

            return new Packages(
                ['icu-libs'],
                ['icu-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                ['^libicu[0-9]+$'],
                ['libicu-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }

    /**
     * The ICU data has been split from the library since Alpine 3.16.
     *
     * @return bool
     */
    private function hasSplitData(OS\Alpine $os)
    {
        return $os->getNumericVersion() >= 316;
    }
}
