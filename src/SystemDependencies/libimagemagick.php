<?php

namespace IPE\SystemDependencies;

use IPE\Installer\State;
use IPE\OS;
use IPE\SystemDependency;
use IPE\SystemPackager\Packages;

final class libimagemagick extends SystemDependency
{
    /**
     * @public
     * @var string
     */
    const HANDLE = 'lib-imagemagick';

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
            $persistent = ['imagemagick', 'libgomp'];
            if ($this->os->getNumericVersion() >= 319) {
                $persistent = array_merge($persistent, ['ghostscript', 'libheif', 'libjxl', 'libraw', 'librsvg']);
            }
            if ($this->os->getNumericVersion() >= 324) {
                $persistent = array_merge($persistent, ['libheif-aom', 'libheif-x265']);
            }

            return new Packages(
                $persistent,
                ['imagemagick-dev']
            );
        }
        if ($this->os instanceof OS\Debian) {
            if ($this->os->getNumericVersion() <= OS\Debian::BOOKWORM) {
                return new Packages(
                    ['^libmagickwand-6.q16-[0-9]+$', '^libmagickcore-6.q16-[0-9]+-extra$', 'ghostscript'],
                    ['libmagickwand-dev']
                );
            }

            return new Packages(
                ['^libmagickwand-7.q16-[0-9]+$', '^libmagickcore-7.q16-[0-9]+-extra$', 'libheif-plugin-aomenc', 'ghostscript'],
                ['libmagickwand-dev']
            );
        }
        if ($this->os instanceof OS\Ubuntu) {
            if ($this->os->getNumericVersion() <= OS\Ubuntu::TRUSTY) {
                return new Packages(
                    ['libmagickwand5', 'libmagickcore5-extra', 'ghostscript'],
                    ['libmagickwand-dev']
                );
            }
            if ($this->os->getNumericVersion() <= OS\Ubuntu::NOBLE) {
                return new Packages(
                    ['^libmagickwand-6.q16-[0-9]+$', '^libmagickcore-6.q16-[0-9]+-extra$', 'ghostscript'],
                    ['libmagickwand-dev']
                );
            }

            return new Packages(
                ['^libmagickwand-7.q16-[0-9]+$', '^libmagickcore-7.q16-[0-9]+-extra$', 'libheif-plugin-aomenc', 'ghostscript'],
                ['libmagickwand-dev']
            );
        }

        throw $this->createUnknownPackagesException();
    }
}
