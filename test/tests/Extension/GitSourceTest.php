<?php

namespace IPE\Test\Extension;

use IPE\Extension\GitSource;
use IPE\GitHost;
use IPE\Test\Support\TestCase;

final class GitSourceTest extends TestCase
{
    /**
     * @return list<array{string, string, string, string}> the URL, and the expected repository, reference and URL of .gitmodules
     */
    public static function provideArchiveURLs()
    {
        return [
            ['https://github.com/phpredis/phpredis/archive/6.3.0.zip', 'https://github.com/phpredis/phpredis.git', '6.3.0', 'https://raw.githubusercontent.com/phpredis/phpredis/6.3.0/.gitmodules'],
            ['https://github.com/phpredis/phpredis/archive/refs/tags/6.3.0.tar.gz', 'https://github.com/phpredis/phpredis.git', 'refs/tags/6.3.0', 'https://raw.githubusercontent.com/phpredis/phpredis/refs/tags/6.3.0/.gitmodules'],
            ['https://codeload.github.com/phpredis/phpredis/tar.gz/refs/heads/develop', 'https://github.com/phpredis/phpredis.git', 'refs/heads/develop', 'https://raw.githubusercontent.com/phpredis/phpredis/refs/heads/develop/.gitmodules'],
            ['https://api.github.com/repos/phpredis/phpredis/zipball/df4fab2', 'https://github.com/phpredis/phpredis.git', 'df4fab2', 'https://raw.githubusercontent.com/phpredis/phpredis/df4fab2/.gitmodules'],
            ['https://gitlab.com/api/v4/projects/group%2Fsubgroup%2Fproject/repository/archive.tar.gz?sha=refs%2Ftags%2Fv1.0', 'https://gitlab.com/group/subgroup/project.git', 'refs/tags/v1.0', 'https://gitlab.com/api/v4/projects/group%2Fsubgroup%2Fproject/repository/files/.gitmodules/raw?ref=refs%2Ftags%2Fv1.0'],
            ['https://gitlab.com/api/v4/projects/group%2Fproject/repository/archive.zip', 'https://gitlab.com/group/project.git', '', 'https://gitlab.com/api/v4/projects/group%2Fproject/repository/files/.gitmodules/raw'],
            ['https://gitlab.com/api/v4/projects/12345/repository/archive.tar.gz?sha=main', '', '', ''],
            ['https://gitlab.com/group/project/-/archive/v1.0/project-v1.0.tar.gz', 'https://gitlab.com/group/project.git', 'v1.0', 'https://gitlab.com/api/v4/projects/group%2Fproject/repository/files/.gitmodules/raw?ref=v1.0'],
            ['https://codeberg.org/owner/repository/archive/main.tar.gz', 'https://codeberg.org/owner/repository.git', 'main', 'https://codeberg.org/api/v1/repos/owner/repository/raw/.gitmodules?ref=main'],
            ['https://bitbucket.org/workspace/repository/get/cafebabecafe.zip', 'https://bitbucket.org/workspace/repository.git', 'cafebabecafe', 'https://bitbucket.org/workspace/repository/raw/cafebabecafe/.gitmodules'],
            ['https://pecl.php.net/get/redis-6.3.0.tgz', '', '', ''],
        ];
    }

    /**
     * @dataProvider provideArchiveURLs
     *
     * @param string $url
     * @param string $expectedURL
     * @param string $expectedReference
     * @param string $expectedGitmodulesURL
     *
     * @return void
     */
    public function testWithArchiveURL($url, $expectedURL, $expectedReference, $expectedGitmodulesURL)
    {
        $gitSource = (new GitSource('/src'))->withArchiveURL($url, (new GitHost\Registry())->registerDefaultHosts());
        self::assertSame('/src', $gitSource->getRoot());
        self::assertSame($expectedURL, $gitSource->getRemoteURL());
        self::assertSame($expectedReference, $gitSource->getRemoteReference());
        self::assertSame($expectedGitmodulesURL, $gitSource->getGitmodulesURL());
    }
}
