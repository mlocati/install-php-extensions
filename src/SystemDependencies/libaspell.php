<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\Option\CustomOption;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libaspell extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-aspell';

    /**
     * @public
     * @var string
     */
    const OPTION_LANGUAGES = 'languages';

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
     * @see \IPE\SystemDependency::getOptions()
     */
    public static function getOptions()
    {
        return [
            new CustomOption(self::OPTION_LANGUAGES, 'The comma-separated list of the languages of the dictionaries to be installed', true, 'en'),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * @see \IPE\SystemDependency::getSystemPackages()
     */
    public function getSystemPackages(State $state)
    {
        $dictionaries = [];
        foreach (preg_split('/[\s,]+/', (string) $this->options->getCustomValue(self::OPTION_LANGUAGES), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $language) {
            $dictionaries[] = "aspell-{$language}";
        }
        if ($this->os instanceof OS\Alpine) {
            return new Packages(
                array_merge(['aspell-libs'], $dictionaries),
                ['aspell-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            return new Packages(
                array_merge(['libaspell15'], $dictionaries),
                ['libpspell-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
