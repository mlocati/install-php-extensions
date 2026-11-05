<?php

namespace IPE\Test;

use IPE\ComposerRepository;
use IPE\Exception;
use IPE\Extension\Version\Lister\Composer;
use IPE\Filesystem\Downloader;
use IPE\Service\JSON;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ComposerRepositoryTest extends TestCase
{
    /**
     * @return void
     */
    public function testPackagistDoesNotNeedPackagesJson()
    {
        $repositories = (new ComposerRepository\Registry())->registerDefaultRepositories()->getRepositories();
        self::assertCount(1, $repositories);
        self::assertSame(ComposerRepository\Registry::PACKAGIST_URL, $repositories[0]->getURL());
        $versions = $repositories[0]->getVersions(self::createDownloader([
            'https://repo.packagist.org/p2/foo/bar.json' => self::buildMetadata('foo/bar', [['version' => '1.0.0', 'type' => 'php-ext']], true),
        ]), 'Foo/Bar', false);
        self::assertNotNull($versions);
        self::assertSame(['1.0.0'], array_map('strval', $versions));
    }

    /**
     * @return void
     */
    public function testInvalidMetadataURL()
    {
        $this->expectException(Exception\InvalidArgument::class);
        new ComposerRepository('https://repo.example.com', 0, 'https://repo.example.com/p2/package.json');
    }

    /**
     * @return void
     */
    public function testPackagesJson()
    {
        $repository = new ComposerRepository('https://repo.example.com/composer/');
        self::assertSame('https://repo.example.com/composer', $repository->getURL());
        $downloader = self::createDownloader([
            'https://repo.example.com/composer/packages.json' => JSON::encode([
                'metadata-url' => '/p2/%package%.json',
                'available-packages' => ['foo/bar'],
                'available-package-patterns' => ['acme/*'],
            ]),
            'https://repo.example.com/p2/foo/bar.json' => self::buildMetadata('foo/bar', [
                ['version' => '2.0.0', 'type' => 'php-ext', 'require' => ['php' => '>=8.0']],
                ['version' => '1.0.0', 'type' => 'php-ext'],
                ['version' => '0.1.0', 'type' => 'library'],
            ], false),
            'https://repo.example.com/p2/foo/bar~dev.json' => self::buildMetadata('foo/bar', [['version' => 'dev-main', 'type' => 'php-ext']], false),
        ]);
        $versions = $repository->getVersions($downloader, 'foo/bar', false);
        self::assertNotNull($versions);
        self::assertSame(['2.0.0', '1.0.0'], array_map('strval', $versions));
        // Not minified: the second version must not inherit the requirements of the first one
        self::assertSame('', $versions[1]->getPHPRequirement());
        $versions = $repository->getVersions($downloader, 'foo/bar', true);
        self::assertNotNull($versions);
        self::assertSame(['2.0.0', '1.0.0', 'dev-main'], array_map(static function ($version) {
            return $version->getVersion();
        }, $versions));
        // Not in the list of the available packages: no request
        self::assertNull($repository->getVersions($downloader, 'foo/baz', false));
        self::assertNull($repository->getVersions($downloader, 'acme/thing', false));
    }

    /**
     * @return void
     */
    public function testURLOfPackagesJson()
    {
        $repository = new ComposerRepository('https://repo.example.com/custom.json');
        $versions = $repository->getVersions(self::createDownloader([
            'https://repo.example.com/custom.json' => JSON::encode(['metadata-url' => 'https://cdn.example.com/%package%.json']),
            'https://cdn.example.com/foo/bar.json' => self::buildMetadata('foo/bar', [['version' => '1.0.0', 'type' => 'php-ext']], false),
        ]), 'foo/bar', false);
        self::assertNotNull($versions);
        self::assertSame(['1.0.0'], array_map('strval', $versions));
    }

    /**
     * @return void
     */
    public function testProtocolVersion1IsNotSupported()
    {
        $repository = new ComposerRepository('https://repo.example.com');
        $this->expectException(Exception\IO::class);
        $this->expectExceptionMessage('missing metadata-url');
        $repository->getVersions(self::createDownloader([
            'https://repo.example.com/packages.json' => JSON::encode(['includes' => ['include/all$0123.json' => ['sha1' => '0123']]]),
        ]), 'foo/bar', false);
    }

    /**
     * @return void
     */
    public function testRegistry()
    {
        $registry = (new ComposerRepository\Registry())->registerDefaultRepositories();
        $registry
            ->register(new ComposerRepository('https://low.example.com', -1))
            ->register(new ComposerRepository('https://first.example.com'))
            ->register(new ComposerRepository('https://high.example.com', 10))
            ->register(new ComposerRepository('https://second.example.com'))
        ;
        $urls = static function (ComposerRepository\Registry $registry) {
            return array_map(static function (ComposerRepository $repository) {
                return $repository->getURL();
            }, $registry->getRepositories());
        };
        self::assertSame(
            ['https://high.example.com', 'https://first.example.com', 'https://second.example.com', 'https://low.example.com', ComposerRepository\Registry::PACKAGIST_URL],
            $urls($registry)
        );
        $registry->unregister(ComposerRepository\Registry::PACKAGIST_URL)->unregister('https://first.example.com/');
        self::assertSame(['https://high.example.com', 'https://second.example.com', 'https://low.example.com'], $urls($registry));
        $this->expectException(Exception\InvalidArgument::class);
        $registry->register(new ComposerRepository('https://high.example.com/', 5));
    }

    /**
     * @return void
     */
    public function testRepositoryWithHigherPriorityWins()
    {
        $downloader = self::createDownloader([
            'https://low.example.com/packages.json' => JSON::encode(['metadata-url' => '/p2/%package%.json']),
            'https://low.example.com/p2/foo/low.json' => self::buildMetadata('foo/low', [['version' => '1.0.0', 'type' => 'php-ext']], false),
            'https://low.example.com/p2/foo/both.json' => self::buildMetadata('foo/both', [['version' => '1.0.0', 'type' => 'php-ext']], false),
            'https://low.example.com/p2/foo/high-empty.json' => self::buildMetadata('foo/high-empty', [['version' => '1.0.0', 'type' => 'php-ext']], false),
            'https://high.example.com/packages.json' => JSON::encode(['metadata-url' => '/p2/%package%.json']),
            'https://high.example.com/p2/foo/both.json' => self::buildMetadata('foo/both', [['version' => '2.0.0', 'type' => 'php-ext']], false),
            'https://high.example.com/p2/foo/high-empty.json' => self::buildMetadata('foo/high-empty', [], false),
        ]);
        $registry = (new ComposerRepository\Registry())
            ->register(new ComposerRepository('https://low.example.com', 1))
            ->register(new ComposerRepository('https://high.example.com', 2))
        ;
        $lister = new Composer($downloader, $registry, new \IPE\Logger());
        self::assertSame(['1.0.0'], array_map('strval', $lister->getVersions('foo', 'low')));
        self::assertSame(['2.0.0'], array_map('strval', $lister->getVersions('foo', 'both')));
        // The repository with the higher priority has the package (without versions): the other one is not used
        self::assertSame([], $lister->getVersions('foo', 'high-empty'));
        self::assertSame([], $lister->getVersions('foo', 'none'));
    }

    /**
     * @param array<string, string|false> $responses
     *
     * @return \IPE\Filesystem\Downloader
     */
    private static function createDownloader(array $responses)
    {
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('getContent')->andReturnUsing(static function ($url) use ($responses) {
            if (is_string($url) && isset($responses[$url]) && $responses[$url] !== false) {
                return $responses[$url];
            }
            throw new Exception\IO\NotFound('Not found');
        });

        return $downloader;
    }

    /**
     * @param non-empty-string $packageName
     * @param list<array<string, mixed>> $versions
     * @param bool $minified
     *
     * @return string
     */
    private static function buildMetadata($packageName, array $versions, $minified)
    {
        $data = [];
        if ($minified) {
            $data['minified'] = 'composer/2.0';
        }
        $data['packages'] = [
            $packageName => array_map(
                static function (array $version) use ($packageName) {
                    return ['name' => $packageName] + $version;
                },
                $versions
            ),
        ];

        return JSON::encode($data);
    }
}
