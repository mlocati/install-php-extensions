<?php

namespace IPE\Dev;

/**
 * The Docker images where install-php-extensions can be tested.
 *
 * @internal
 */
final class DockerImages
{
    /**
     * From the oldest to the most recent Alpine, then from the oldest to the most recent Debian, then the systems where PHP is installed by the system packager.
     *
     * @return non-empty-list<non-empty-string>
     */
    public static function getOperatingSystems()
    {
        return [
            'alpine3.9',
            'alpine3.10',
            'alpine3.11',
            'alpine3.12',
            'alpine3.13',
            'alpine3.14',
            'alpine3.15',
            'alpine3.16',
            'alpine3.17',
            'alpine3.18',
            'alpine3.19',
            'alpine3.20',
            'alpine3.21',
            'alpine3.22',
            'alpine3.23',
            'alpine3.24',
            'jessie',
            'stretch',
            'buster',
            'bullseye',
            'bookworm',
            'trixie',
            'alpine3.24-apk',
            'bookworm-apt',
            'trixie-apt',
            'trixie-sury',
            'ubuntu22.04-apt',
            'ubuntu24.04-apt',
            'ubuntu24.04-sury',
            'ubuntu26.04-apt',
        ];
    }

    /**
     * @return list<\IPE\Dev\DockerImage> sorted like getOperatingSystems()
     */
    public static function getAll()
    {
        $result = [
            new DockerImage('php:5.5-cli', 'jessie', '5.5', false, false),
            new DockerImage('php:5.5-zts', 'jessie', '5.5', true, false),
        ];
        foreach (self::getOfficialImageDefinitions() as $phpVersion => $definition) {
            $rc = isset($definition['rc']) && $definition['rc'];
            $withoutZTS = isset($definition['withoutZTS']) ? $definition['withoutZTS'] : [];
            foreach ($definition['os'] as $os) {
                $result[] = new DockerImage("php:{$phpVersion}" . ($rc ? '-rc' : '') . "-cli-{$os}", $os, $phpVersion, false, $rc);
                if (!in_array($os, $withoutZTS, true)) {
                    $result[] = new DockerImage("php:{$phpVersion}" . ($rc ? '-rc' : '') . "-zts-{$os}", $os, $phpVersion, true, $rc);
                }
            }
        }
        $result = array_merge($result, self::getSystemImages());
        $osOrder = array_flip(self::getOperatingSystems());
        usort($result, static function (DockerImage $a, DockerImage $b) use ($osOrder) {
            return $osOrder[$a->getOS()] - $osOrder[$b->getOS()] ?: version_compare($a->getPHPVersion(), $b->getPHPVersion()) ?: (int) $a->isZTS() - (int) $b->isZTS();
        });

        return $result;
    }

    /**
     * @param string $phpVersion major.minor
     * @param bool $zts
     *
     * @return list<\IPE\Dev\DockerImage> sorted like getOperatingSystems()
     */
    public static function find($phpVersion, $zts)
    {
        return array_values(array_filter(self::getAll(), static function (DockerImage $image) use ($phpVersion, $zts) {
            return $image->getPHPVersion() === $phpVersion && $image->isZTS() === $zts;
        }));
    }

    /**
     * Not tested: they stopped at one of the first releases of the PHP version.
     *
     * @return array<non-empty-string, non-empty-list<non-empty-string>> the keys are the PHP versions (major.minor), the values are the operating systems
     */
    public static function getAbandonedOfficialImages()
    {
        $result = [];
        foreach (self::getOfficialImageDefinitions() as $phpVersion => $definition) {
            if (isset($definition['abandoned'])) {
                $result[$phpVersion] = $definition['abandoned'];
            }
        }

        return $result;
    }

    /**
     * The images with PHP installed by the system packager, built locally.
     *
     * @return list<\IPE\Dev\DockerImage>
     */
    private static function getSystemImages()
    {
        $result = [];
        foreach (['8.3', '8.4', '8.5'] as $phpVersion) {
            $package = 'php' . str_replace('.', '', $phpVersion);
            // Unlike APT, APK doesn't include the phar extension in the main package
            $result[] = self::createSystemImage('alpine3.24-apk', $phpVersion, 'alpine:3.24', ["apk add --no-cache {$package} {$package}-phar"], $package);
        }
        $aptImages = [
            'bookworm-apt' => ['debian:bookworm', '8.2'],
            'trixie-apt' => ['debian:trixie', '8.4'],
            'ubuntu22.04-apt' => ['ubuntu:22.04', '8.1'],
            'ubuntu24.04-apt' => ['ubuntu:24.04', '8.3'],
            'ubuntu26.04-apt' => ['ubuntu:26.04', '8.5'],
        ];
        foreach ($aptImages as $os => $data) {
            list($baseImage, $phpVersion) = $data;
            $result[] = self::createSystemImage($os, $phpVersion, $baseImage, self::getAPTCommands([], $phpVersion));
        }
        // See https://packages.sury.org/php/README.txt
        $surySetup = [
            'DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends ca-certificates curl',
            'curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb',
            'dpkg -i /tmp/debsuryorg-archive-keyring.deb',
            '. /etc/os-release && echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ $VERSION_CODENAME main" >/etc/apt/sources.list.d/php.list',
            'apt-get update',
        ];
        foreach (['trixie-sury' => 'debian:trixie', 'ubuntu24.04-sury' => 'ubuntu:24.04'] as $os => $baseImage) {
            foreach (['5.6', '7.0', '7.1', '7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3', '8.4', '8.5'] as $phpVersion) {
                $result[] = self::createSystemImage($os, $phpVersion, $baseImage, self::getAPTCommands($surySetup, $phpVersion));
            }
        }

        return $result;
    }

    /**
     * @param non-empty-string $os
     * @param non-empty-string $phpVersion
     * @param non-empty-string $baseImage
     * @param list<non-empty-string> $setupCommands
     * @param string $phpBinary
     *
     * @return \IPE\Dev\DockerImage
     */
    private static function createSystemImage($os, $phpVersion, $baseImage, array $setupCommands, $phpBinary = '')
    {
        return new DockerImage("ipe-test:{$os}-php{$phpVersion}", $os, $phpVersion, false, false, $baseImage, $setupCommands, $phpBinary);
    }

    /**
     * @param list<non-empty-string> $repositorySetup the commands that add a repository, after the first refresh of the package lists
     * @param non-empty-string $phpVersion
     *
     * @return list<non-empty-string>
     */
    private static function getAPTCommands(array $repositorySetup, $phpVersion)
    {
        return array_merge(
            ['apt-get update'],
            $repositorySetup,
            [
                "DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends php{$phpVersion}-cli",
                // Like most Dockerfiles: IPE must refresh the package lists
                'rm -rf /var/lib/apt/lists/*',
            ]
        );
    }

    /**
     * The official Docker PHP images (php:<version>[-rc]-<cli|zts>-<os>) of the operating systems supported by IPE.
     * Keys:
     * - rc: true if the PHP version has not been released yet
     * - os: the operating systems
     * - withoutZTS: the operating systems without the zts images
     * - abandoned: the operating systems whose images stopped at one of the first releases of the PHP version (not included in os).
     *
     * @return array<non-empty-string, array{rc?: bool, os: non-empty-list<non-empty-string>, withoutZTS?: list<non-empty-string>, abandoned?: non-empty-list<non-empty-string>}>
     */
    private static function getOfficialImageDefinitions()
    {
        return [
            '5.6' => [
                'os' => ['jessie', 'stretch'],
            ],
            '7.0' => [
                'os' => ['jessie', 'stretch'],
            ],
            '7.1' => [
                'os' => ['alpine3.9', 'alpine3.10', 'jessie', 'stretch', 'buster'],
            ],
            '7.2' => [
                'os' => ['alpine3.9', 'alpine3.10', 'alpine3.11', 'alpine3.12', 'stretch', 'buster'],
            ],
            '7.3' => [
                'os' => ['alpine3.9', 'alpine3.10', 'alpine3.11', 'alpine3.12', 'alpine3.13', 'alpine3.14', 'alpine3.15', 'stretch', 'buster', 'bullseye'],
            ],
            '7.4' => [
                'os' => ['alpine3.10', 'alpine3.11', 'alpine3.12', 'alpine3.13', 'alpine3.14', 'alpine3.15', 'alpine3.16', 'buster', 'bullseye'],
            ],
            '8.0' => [
                'os' => ['alpine3.13', 'alpine3.14', 'alpine3.15', 'alpine3.16', 'buster', 'bullseye'],
                'withoutZTS' => ['alpine3.13'],
                'abandoned' => ['alpine3.12'],
            ],
            '8.1' => [
                'os' => ['alpine3.14', 'alpine3.15', 'alpine3.16', 'alpine3.17', 'alpine3.18', 'alpine3.19', 'alpine3.20', 'alpine3.21', 'alpine3.22', 'buster', 'bullseye', 'bookworm', 'trixie'],
            ],
            '8.2' => [
                'os' => ['alpine3.16', 'alpine3.17', 'alpine3.18', 'alpine3.19', 'alpine3.20', 'alpine3.21', 'alpine3.22', 'alpine3.23', 'alpine3.24', 'buster', 'bullseye', 'bookworm', 'trixie'],
            ],
            '8.3' => [
                'os' => ['alpine3.18', 'alpine3.19', 'alpine3.20', 'alpine3.21', 'alpine3.22', 'alpine3.23', 'alpine3.24', 'bullseye', 'bookworm', 'trixie'],
                'abandoned' => ['alpine3.17'],
            ],
            '8.4' => [
                'os' => ['alpine3.20', 'alpine3.21', 'alpine3.22', 'alpine3.23', 'alpine3.24', 'bullseye', 'bookworm', 'trixie'],
                'abandoned' => ['alpine3.19'],
            ],
            '8.5' => [
                'os' => ['alpine3.22', 'alpine3.23', 'alpine3.24', 'bookworm', 'trixie'],
                'abandoned' => ['alpine3.21'],
            ],
            '8.6' => [
                'rc' => true,
                'os' => ['alpine3.23', 'alpine3.24', 'bookworm', 'trixie'],
            ],
        ];
    }
}
