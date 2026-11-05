<?php

namespace IPE\Test\Extension\Source;

use IPE\Exception;
use IPE\Extension\Source\Pecl;
use IPE\Extension\Version;
use IPE\Filesystem\ArchiveExtractor;
use IPE\Filesystem\ArchiveFetcher;
use IPE\Filesystem\Downloader;
use IPE\PHPInfo;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class PeclTest extends TestCase
{
    const RELEASES = '<a><p>foo</p><c>pecl.php.net</c><r><v>3.0.0</v><s>beta</s></r><r><v>2.0.0</v><s>stable</s></r><r><v>1.1.0</v><s>stable</s></r><r><v>1.0.0</v><s>stable</s></r></a>';

    const CATEGORY = 'Tools and Utilities';

    const CATEGORY_URL = 'https://pecl.php.net/rest/c/Tools+and+Utilities/packagesinfo.xml';

    /**
     * @param string $minimumPHP
     *
     * @return string
     */
    private static function serializeDependencies($minimumPHP)
    {
        return serialize(['required' => ['php' => ['min' => $minimumPHP]]]);
    }

    /**
     * @param array<string, string> $minimums the keys are the versions, the values are the minimum PHP versions
     *
     * @return string
     */
    private static function buildPackagesInfo(array $minimums)
    {
        $result = '<f><pi><p><n>FOO</n></p><?xml version="1.0" encoding="UTF-8" ?><a><p>foo</p></a>';
        foreach ($minimums as $version => $minimum) {
            $result .= "<deps><v>{$version}</v><d>" . htmlspecialchars(self::serializeDependencies($minimum), ENT_QUOTES) . '</d></deps>';
        }

        return $result . '</pi></f>';
    }

    /**
     * @return \IPE\Filesystem\Downloader&\Mockery\MockInterface
     */
    private static function createDownloader()
    {
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('getContent')->with('https://pecl.php.net/rest/r/foo/allreleases.xml')->andReturn(self::RELEASES);

        return $downloader;
    }

    /**
     * @return \IPE\Extension\Source\Pecl
     */
    private static function createSource(Downloader $downloader)
    {
        return new Pecl(new Version\Lister\Pecl($downloader), new Version\Selector(new \IPE\Logger()), new ArchiveFetcher($downloader, M::mock(ArchiveExtractor::class), new \IPE\Logger()), new \IPE\Logger());
    }

    /**
     * @param string $version
     *
     * @return \IPE\PHPInfo
     */
    private static function createPHP($version)
    {
        $php = M::mock(PHPInfo::class);
        $php->shouldReceive('getVersion')->andReturn($version);
        $php->shouldReceive('getBaseVersion')->andReturn($version);

        return $php;
    }

    /**
     * @return void
     */
    public function testTheCategoryIsNotDownloadedIfTheNewestVersionIsCompatible()
    {
        $downloader = self::createDownloader();
        $downloader->shouldReceive('getContent')->once()->with('https://pecl.php.net/rest/r/foo/deps.2.0.0.txt')->andReturn(self::serializeDependencies('7.0.0'));
        $downloader->shouldNotReceive('getContent')->with(self::CATEGORY_URL);
        $version = self::createSource($downloader)->selectVersion('foo', self::CATEGORY, '', self::createPHP('8.4.1'));
        self::assertNotNull($version);
        self::assertSame('2.0.0', $version->getVersion());
    }

    /**
     * @return void
     */
    public function testOlderVersionsAreReadFromTheCategory()
    {
        $downloader = self::createDownloader();
        $downloader->shouldReceive('getContent')->once()->with('https://pecl.php.net/rest/r/foo/deps.2.0.0.txt')->andReturn(self::serializeDependencies('8.0.0'));
        $downloader->shouldReceive('getContent')->once()->with(self::CATEGORY_URL)->andReturn(self::buildPackagesInfo(['2.0.0' => '8.0.0', '1.1.0' => '7.4.0', '1.0.0' => '7.0.0']));
        $version = self::createSource($downloader)->selectVersion('foo', self::CATEGORY, '', self::createPHP('7.1.33'));
        self::assertNotNull($version);
        self::assertSame('1.0.0', $version->getVersion());
    }

    /**
     * @return void
     */
    public function testNoCompatibleVersions()
    {
        $downloader = self::createDownloader();
        $downloader->shouldReceive('getContent')->once()->with('https://pecl.php.net/rest/r/foo/deps.2.0.0.txt')->andReturn(self::serializeDependencies('8.0.0'));
        $downloader->shouldReceive('getContent')->once()->with(self::CATEGORY_URL)->andReturn(self::buildPackagesInfo(['1.1.0' => '8.0.0', '1.0.0' => '8.0.0']));
        $whyNone = '';
        self::assertNull(self::createSource($downloader)->selectVersion('foo', self::CATEGORY, '', self::createPHP('7.1.33'), null, $whyNone));
        self::assertSame('no version is compatible with PHP 7.1.33 (the newest, 2.0.0, requires PHP >=8.0.0)', $whyNone);
    }

    /**
     * @return void
     */
    public function testWhyNoVersionIsSelected()
    {
        $source = self::createSource(self::createDownloader());
        $whyNone = '';
        self::assertNull($source->selectVersion('foo', self::CATEGORY, '^4', null, null, $whyNone));
        self::assertSame('no version satisfies the version requirements (the newest is 3.0.0 (beta))', $whyNone);
        self::assertNull($source->selectVersion('foo', self::CATEGORY, '^3', null, null, $whyNone));
        self::assertSame('the versions that satisfy the version requirements are less stable than stable (the newest is 3.0.0 (beta)): use @beta in the version requirements to accept it', $whyNone);
        self::assertNull($source->selectVersion('foo', self::CATEGORY, '', null, static function () { return false; }, $whyNone));
        self::assertSame('none of the versions can be used', $whyNone);
        self::assertNotNull($source->selectVersion('foo', self::CATEGORY, '', null, null, $whyNone));
        self::assertSame('', $whyNone);
    }

    /**
     * @return void
     */
    public function testVersionsMissingFromTheCategoryAreReadFromTheirOwnFile()
    {
        $downloader = self::createDownloader();
        $downloader->shouldReceive('getContent')->once()->with('https://pecl.php.net/rest/r/foo/deps.2.0.0.txt')->andReturn(self::serializeDependencies('8.0.0'));
        $downloader->shouldReceive('getContent')->once()->with(self::CATEGORY_URL)->andThrow(new Exception\IO\NotFound('Not found'));
        $downloader->shouldReceive('getContent')->once()->with('https://pecl.php.net/rest/r/foo/deps.1.1.0.txt')->andReturn(self::serializeDependencies('7.0.0'));
        $version = self::createSource($downloader)->selectVersion('foo', self::CATEGORY, '', self::createPHP('7.1.33'));
        self::assertNotNull($version);
        self::assertSame('1.1.0', $version->getVersion());
    }

    /**
     * @return void
     */
    public function testUserRequirementsAreHonoured()
    {
        $downloader = self::createDownloader();
        $downloader->shouldReceive('getContent')->once()->with('https://pecl.php.net/rest/r/foo/deps.1.1.0.txt')->andReturn(self::serializeDependencies('7.0.0'));
        $version = self::createSource($downloader)->selectVersion('foo', self::CATEGORY, '^1.0', self::createPHP('7.1.33'));
        self::assertNotNull($version);
        self::assertSame('1.1.0', $version->getVersion());
    }

    /**
     * @return void
     */
    public function testVersionsWithoutRequirementsAreConsideredCompatible()
    {
        $downloader = self::createDownloader();
        $downloader->shouldReceive('getContent')->once()->with('https://pecl.php.net/rest/r/foo/deps.2.0.0.txt')->andThrow(new Exception\IO\NotFound('Not found'));
        $version = self::createSource($downloader)->selectVersion('foo', self::CATEGORY, '', self::createPHP('5.5.38'));
        self::assertNotNull($version);
        self::assertSame('2.0.0', $version->getVersion());
    }

    /**
     * @return void
     */
    public function testCompatibilityIsNotCheckedWithoutPHP()
    {
        $version = self::createSource(self::createDownloader())->selectVersion('foo', self::CATEGORY, '');
        self::assertNotNull($version);
        self::assertSame('2.0.0', $version->getVersion());
    }
}
