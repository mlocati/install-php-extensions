<?php

namespace IPE\Test\Extension\Version\Lister;

use IPE\Exception;
use IPE\Extension\Version\Lister\Pecl as Lister;
use IPE\Extension\Version\Pecl as Version;
use IPE\Filesystem\Downloader;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class PeclTest extends TestCase
{
    /**
     * @return void
     */
    public function testListingVersions()
    {
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('getContent')->once()->andReturn(
            <<<'EOT'
<?xml version="1.0" encoding="UTF-8" ?>
<a
    xmlns="http://pear.php.net/dtd/rest.allreleases"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xlink="http://www.w3.org/1999/xlink"
    xsi:schemaLocation="http://pear.php.net/dtd/rest.allreleases http://pear.php.net/dtd/rest.allreleases.xsd"
>
    <p>foo</p>
    <c>pecl.php.net</c>
    <r><v>1.4.1</v><s>stable</s></r>
    <r><v>1.4.0</v><s>stable</s></r>
    <r><v>1.3.1</v><s>beta</s></r>
    <r><v>1.3.0</v><s>alpha</s></r>
    <r><v>1.2</v><s>devel</s></r>
    <r><v>1.1</v><s>snapshot</s></r>
    <r><v>1.0.1</v><s>stable</s></r>
    <r><v>1.0</v><s>stable</s></r>
</a>
EOT
        );
        $lister = new Lister($downloader);
        $actualVersions = $lister->getVersions('foo', true, true);
        self::assertSame(
            [
                '1.4.1',
                '1.4.0',
                '1.3.1 (beta)',
                '1.3.0 (alpha)',
                '1.2 (dev)',
                '1.1 (dev)',
                '1.0.1',
                '1.0',
            ],
            array_map(
                static function (Version $v) {
                    return (string) $v;
                },
                $actualVersions
            )
        );
        self::assertSame('foo', $actualVersions[0]->getPeclName());
        self::assertSame('https://pecl.php.net/get/foo/1.4.1', $actualVersions[0]->getDownloadURL());
    }

    /**
     * @return array<array{string, string}>
     */
    public static function providePHPRequirementCases()
    {
        return [
            'min only' => ['a:1:{s:8:"required";a:2:{s:3:"php";a:1:{s:3:"min";s:5:"8.3.0";}s:13:"pearinstaller";a:1:{s:3:"min";s:4:"1.10";}}}', '>=8.3.0'],
            'min and max' => ['a:1:{s:8:"required";a:1:{s:3:"php";a:2:{s:3:"min";s:5:"7.2.0";s:3:"max";s:6:"8.1.99";}}}', '>=7.2.0 <=8.1.99'],
            'one exclude' => ['a:1:{s:8:"required";a:1:{s:3:"php";a:2:{s:3:"min";s:5:"7.2.0";s:7:"exclude";s:5:"8.4.0";}}}', '>=7.2.0 !=8.4.0'],
            'many excludes' => ['a:1:{s:8:"required";a:1:{s:3:"php";a:1:{s:7:"exclude";a:2:{i:0;s:5:"7.0.0";i:1;s:5:"7.0.1";}}}}', '!=7.0.0 !=7.0.1'],
            'package.xml 1.0' => ['a:2:{i:1;a:3:{s:4:"type";s:3:"php";s:3:"rel";s:2:"ge";s:7:"version";s:5:"5.0.3";}i:2;a:4:{s:4:"type";s:3:"ext";s:3:"rel";s:2:"ge";s:7:"version";s:3:"1.0";s:4:"name";s:3:"pdo";}}', '>=5.0.3'],
            'no php' => ['a:1:{s:8:"required";a:1:{s:13:"pearinstaller";a:1:{s:3:"min";s:4:"1.10";}}}', ''],
            'invalid' => ['not serialized', ''],
            'objects' => ['a:1:{i:0;O:8:"stdClass":0:{}}', ''],
        ];
    }

    /**
     * @dataProvider providePHPRequirementCases
     *
     * @param string $serialized
     * @param string $expected
     *
     * @return void
     */
    public function testPHPRequirement($serialized, $expected)
    {
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('getContent')->once()->with('https://pecl.php.net/rest/r/foo/deps.1.2.3.txt')->andReturn($serialized);
        self::assertSame($expected, (new Lister($downloader))->getPHPRequirement(new Version('FOO', '1.2.3', 'stable')));
    }

    /**
     * @return void
     */
    public function testPHPRequirementsOfTheCategoryAreDownloadedOnce()
    {
        $packagesInfo = <<<'EOT'
<?xml version="1.0" encoding="UTF-8" ?>
<f>
<pi>
    <p><n>FOO</n> <c>pecl.php.net</c></p><?xml version="1.0" encoding="UTF-8" ?>
    <a><p>foo</p><r><v>1.1</v><s>stable</s></r></a>
    <deps>
        <v>1.1</v>
        <d>a:1:{s:8:&quot;required&quot;;a:1:{s:3:&quot;php&quot;;a:1:{s:3:&quot;min&quot;;s:5:&quot;7.0.0&quot;;}}}</d>
    </deps>
    <deps><v>1.0</v><d>a:1:{i:1;a:3:{s:4:&quot;type&quot;;s:3:&quot;php&quot;;s:3:&quot;rel&quot;;s:2:&quot;ge&quot;;s:7:&quot;version&quot;;s:5:&quot;5.0.3&quot;;}}</d></deps>
    <deps><v>0.9</v><d>b:0;</d></deps>
</pi>
<pi>
    <p><n>bar</n></p>
    <deps><v>2.0</v><d>a:1:{s:8:&quot;required&quot;;a:1:{s:3:&quot;php&quot;;a:1:{s:3:&quot;max&quot;;s:5:&quot;8.0.0&quot;;}}}</d></deps>
</pi>
</f>
EOT;
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('getContent')->once()->with('https://pecl.php.net/rest/c/File+Formats/packagesinfo.xml')->andReturn($packagesInfo);
        $downloader->shouldReceive('getContent')->once()->with('https://pecl.php.net/rest/c/PHP/packagesinfo.xml')->andThrow(new Exception\IO\NotFound('Not found'));
        $lister = new Lister($downloader);
        self::assertSame(['1.1' => '>=7.0.0', '1.0' => '>=5.0.3', '0.9' => ''], $lister->getPHPRequirements('foo', 'File Formats'));
        self::assertSame(['2.0' => '<=8.0.0'], $lister->getPHPRequirements('BAR', 'File Formats'));
        self::assertSame([], $lister->getPHPRequirements('unknown', 'File Formats'));
        self::assertSame([], $lister->getPHPRequirements('foo', 'PHP'));
        self::assertSame([], $lister->getPHPRequirements('foo', 'PHP'));
    }

    /**
     * @return void
     */
    public function testPHPRequirementNotFound()
    {
        $downloader = M::mock(Downloader::class);
        $downloader->shouldReceive('getContent')->once()->andThrow(new Exception\IO\NotFound('Not found'));
        self::assertSame('', (new Lister($downloader))->getPHPRequirement(new Version('foo', '1.2.3', 'stable')));
    }
}
