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
     * From the oldest to the most recent Alpine, then from the oldest to the most recent Debian.
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
     * The official Docker PHP images (php:<version>[-rc]-<cli|zts>-<os>) of the operating systems supported by IPE.
     * Keys:
     * - rc: true if the PHP version has not been released yet
     * - os: the operating systems
     * - withoutZTS: the operating systems without the zts images.
     *
     * @return array<non-empty-string, array{rc?: bool, os: non-empty-list<non-empty-string>, withoutZTS?: list<non-empty-string>}>
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
                'os' => ['alpine3.12', 'alpine3.13', 'alpine3.14', 'alpine3.15', 'alpine3.16', 'buster', 'bullseye'],
                'withoutZTS' => ['alpine3.12', 'alpine3.13'],
            ],
            '8.1' => [
                'os' => ['alpine3.14', 'alpine3.15', 'alpine3.16', 'alpine3.17', 'alpine3.18', 'alpine3.19', 'alpine3.20', 'alpine3.21', 'alpine3.22', 'buster', 'bullseye', 'bookworm', 'trixie'],
            ],
            '8.2' => [
                'os' => ['alpine3.16', 'alpine3.17', 'alpine3.18', 'alpine3.19', 'alpine3.20', 'alpine3.21', 'alpine3.22', 'alpine3.23', 'alpine3.24', 'buster', 'bullseye', 'bookworm', 'trixie'],
            ],
            '8.3' => [
                'os' => ['alpine3.17', 'alpine3.18', 'alpine3.19', 'alpine3.20', 'alpine3.21', 'alpine3.22', 'alpine3.23', 'alpine3.24', 'bullseye', 'bookworm', 'trixie'],
            ],
            '8.4' => [
                'os' => ['alpine3.19', 'alpine3.20', 'alpine3.21', 'alpine3.22', 'alpine3.23', 'alpine3.24', 'bullseye', 'bookworm', 'trixie'],
            ],
            '8.5' => [
                'os' => ['alpine3.21', 'alpine3.22', 'alpine3.23', 'alpine3.24', 'bookworm', 'trixie'],
            ],
            '8.6' => [
                'rc' => true,
                'os' => ['alpine3.23', 'alpine3.24', 'bookworm', 'trixie'],
            ],
        ];
    }
}
