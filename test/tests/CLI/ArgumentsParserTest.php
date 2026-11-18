<?php

namespace IPE\Test\CLI;

use IPE\CLI\ArgumentsParser;
use IPE\Extension\Asked;
use IPE\Filesystem;
use IPE\GitHost;
use IPE\Test\Support\TestCase;
use Mockery as M;

final class ArgumentsParserTest extends TestCase
{
    /**
     * @return list<array{\IPE\CLI\ArgumentsParser, list<non-empty-string>, list<array{class: class-string<\IPE\Extension\Asked>, getters: array<string, mixed>}>}>
     */
    public static function provideValidTestCases()
    {
        $fs = M::mock(Filesystem::class);
        $fs->shouldReceive('getDirectorySeparator')->zeroOrMoreTimes()->andReturn('/');
        $fs->shouldReceive('exists')->zeroOrMoreTimes()->andReturn(true);
        $fs->shouldReceive('isReadable')->zeroOrMoreTimes()->andReturnUsing(static function ($path) {
            return $path === '/absolute/path/to/ext';
        });
        $fs->shouldReceive('isDirectory')->zeroOrMoreTimes()->andReturnUsing(static function ($path) {
            return $path === '/absolute/path/to/ext';
        });
        foreach ([
            '/absolute/path/to/ext' => '/absolute/path/to/ext',
            '/absolute/path/to/ext/' => '/absolute/path/to/ext',
            './path/to/ext' => '/absolute/path/to/ext',
            '../path/to/ext' => '/absolute/path/to/ext',
        ] as $path => $realPath) {
            $fs->shouldReceive('getRealPath')->with($path)->zeroOrMoreTimes()->andReturn($realPath);
        }
        $parser = new ArgumentsParser($fs, (new GitHost\Registry())->registerDefaultHosts());

        return [
            [
                $parser,
                [],
                [],
            ],
            [
                $parser,
                ['/absolute/path/to/ext'],
                [
                    [
                        'class' => Asked\FromDirectory::class,
                        'getters' => [
                            'getPath' => '/absolute/path/to/ext',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['/absolute/path/to/ext/'],
                [
                    [
                        'class' => Asked\FromDirectory::class,
                        'getters' => [
                            'getPath' => '/absolute/path/to/ext',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['./path/to/ext'],
                [
                    [
                        'class' => Asked\FromDirectory::class,
                        'getters' => [
                            'getPath' => '/absolute/path/to/ext',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['../path/to/ext'],
                [
                    [
                        'class' => Asked\FromDirectory::class,
                        'getters' => [
                            'getPath' => '/absolute/path/to/ext',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['handle'],
                [
                    [
                        'class' => Asked\FromHandle::class,
                        'getters' => [
                            'getVersionRequirements' => '',
                            'getHandle' => 'handle',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['handle:'],
                [
                    [
                        'class' => Asked\FromHandle::class,
                        'getters' => [
                            'getVersionRequirements' => '',
                            'getHandle' => 'handle',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['handle:^1'],
                [
                    [
                        'class' => Asked\FromHandle::class,
                        'getters' => [
                            'getVersionRequirements' => '^1',
                            'getHandle' => 'handle',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['vendor/name'],
                [
                    [
                        'class' => Asked\FromComposer::class,
                        'getters' => [
                            'getVersionRequirements' => '',
                            'getVendor' => 'vendor',
                            'getName' => 'name',
                            'getFullName' => 'vendor/name',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['vendor/name:dev-main'],
                [
                    [
                        'class' => Asked\FromComposer::class,
                        'getters' => [
                            'getVersionRequirements' => 'dev-main',
                            'getVendor' => 'vendor',
                            'getName' => 'name',
                            'getFullName' => 'vendor/name',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['https://www.example.com/tarball.tgz'],
                [
                    [
                        'class' => Asked\FromURL::class,
                        'getters' => [
                            'getURL' => 'https://www.example.com/tarball.tgz',
                            'getSubdir' => '',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['https://www.example.com/tarball.tgz#'],
                [
                    [
                        'class' => Asked\FromURL::class,
                        'getters' => [
                            'getURL' => 'https://www.example.com/tarball.tgz',
                            'getSubdir' => '',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['https://www.example.com/tarball.tgz#path/to/subdir'],
                [
                    [
                        'class' => Asked\FromURL::class,
                        'getters' => [
                            'getURL' => 'https://www.example.com/tarball.tgz',
                            'getSubdir' => 'path/to/subdir',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['https://www.example.com/tarball.tgz?qs#hash#path/to/subdir'],
                [
                    [
                        'class' => Asked\FromURL::class,
                        'getters' => [
                            'getURL' => 'https://www.example.com/tarball.tgz?qs#hash',
                            'getSubdir' => 'path/to/subdir',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['https://www.example.com/tarball.tgz?qs#hash#/path/to/subdir/'],
                [
                    [
                        'class' => Asked\FromURL::class,
                        'getters' => [
                            'getURL' => 'https://www.example.com/tarball.tgz?qs#hash',
                            'getSubdir' => 'path/to/subdir',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['gh:organization/repository@main'],
                [
                    [
                        'class' => Asked\FromURL::class,
                        'getters' => [
                            'getURL' => 'https://codeload.github.com/organization/repository/tar.gz/main',
                            'getSubdir' => '',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['gh:organization/repository@refs/tags/v3.2.0RC2#path/to/subdir'],
                [
                    [
                        'class' => Asked\FromURL::class,
                        'getters' => [
                            'getURL' => 'https://codeload.github.com/organization/repository/tar.gz/refs/tags/v3.2.0RC2',
                            'getSubdir' => 'path/to/subdir',
                        ],
                    ],
                ],
            ],
            [
                $parser,
                ['/absolute/path/to/ext', 'gh:organization/repository@refs/tags/v3.2.0RC2#path/to/subdir', '/absolute/path/to/ext'],
                [
                    [
                        'class' => Asked\FromDirectory::class,
                        'getters' => [
                            'getPath' => '/absolute/path/to/ext',
                        ],
                    ],
                    [
                        'class' => Asked\FromURL::class,
                        'getters' => [
                            'getURL' => 'https://codeload.github.com/organization/repository/tar.gz/refs/tags/v3.2.0RC2',
                            'getSubdir' => 'path/to/subdir',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideValidTestCases
     *
     * @param list<non-empty-string> $arguments
     * @param list<array{class: class-string<\IPE\Extension\Asked>, getters: array<string, mixed>}> $expectedAskedList
     *
     * @return void
     */
    public function testValidCases(ArgumentsParser $parser, array $arguments, array $expectedAskedList)
    {
        $actualAskedList = $parser->parseArguments($arguments);
        self::assertSame(array_keys($expectedAskedList), array_keys($expectedAskedList));
        foreach ($expectedAskedList as $index => $expectedAsked) {
            $actualAsked = $actualAskedList[$index];
            $actualAskedClass = get_class($actualAsked);
            self::assertInstanceOf($expectedAsked['class'], $actualAsked);
            foreach ($expectedAsked['getters'] as $getterName => $expectedValue) {
                $actualValue = $actualAsked->{$getterName}();
                self::assertSame($expectedValue, $actualValue, "Result of {$actualAskedClass}::{$getterName}()");
            }
        }
    }

    /**
     * @return list<array{non-empty-string, non-empty-string, string}> the argument, the expected URL and the expected subdirectory
     */
    public static function provideGitHostShortcuts()
    {
        return [
            ['gh:organization/repository@main', 'https://codeload.github.com/organization/repository/tar.gz/main', ''],
            ['gl:group/project@v1.0.0', 'https://gitlab.com/api/v4/projects/group%2Fproject/repository/archive.tar.gz?sha=v1.0.0', ''],
            ['gl:group/subgroup/project@refs/heads/feature/x#ext', 'https://gitlab.com/api/v4/projects/group%2Fsubgroup%2Fproject/repository/archive.tar.gz?sha=refs%2Fheads%2Ffeature%2Fx', 'ext'],
            ['cb:owner/repository@cafebabecafe', 'https://codeberg.org/owner/repository/archive/cafebabecafe.tar.gz', ''],
            ['bb:workspace/repository@refs/tags/1.2.3#path/to/ext', 'https://bitbucket.org/workspace/repository/get/refs/tags/1.2.3.tar.gz', 'path/to/ext'],
        ];
    }

    /**
     * @dataProvider provideGitHostShortcuts
     *
     * @param non-empty-string $argument
     * @param non-empty-string $expectedURL
     * @param string $expectedSubdir
     *
     * @return void
     */
    public function testGitHostShortcuts($argument, $expectedURL, $expectedSubdir)
    {
        $asked = (new ArgumentsParser(M::mock(Filesystem::class), (new GitHost\Registry())->registerDefaultHosts()))->parseArgument($argument);
        self::assertInstanceOf(Asked\FromURL::class, $asked);
        self::assertSame($expectedURL, $asked->getURL());
        self::assertSame($expectedSubdir, $asked->getSubdir());
    }

    /**
     * @return list<array{non-empty-string, non-empty-string}> the argument, and the expected error message
     */
    public static function provideInvalidGitHostShortcuts()
    {
        return [
            ['gh:organization/repository', "'gh:organization/repository' is not a valid GitHub shortcut"],
            ['gh:organization/sub/repository@main', "'gh:organization/sub/repository@main' is not a valid GitHub shortcut"],
            ['gl:project@main', "'gl:project@main' is not a valid GitLab shortcut"],
            ['cb:../repository@main', "'cb:../repository@main' is not a valid Codeberg shortcut"],
            ['bb:workspace/repository@', "'bb:workspace/repository@' is not a valid Bitbucket shortcut"],
        ];
    }

    /**
     * @dataProvider provideInvalidGitHostShortcuts
     *
     * @param non-empty-string $argument
     * @param non-empty-string $expectedMessage
     *
     * @return void
     */
    public function testInvalidGitHostShortcuts($argument, $expectedMessage)
    {
        $parser = new ArgumentsParser(M::mock(Filesystem::class), (new GitHost\Registry())->registerDefaultHosts());
        $this->expectException(\IPE\Exception\InvalidArgument::class);
        $this->expectExceptionMessage($expectedMessage);
        $parser->parseArgument($argument);
    }

    /**
     * @return void
     */
    public function testComposerOptions()
    {
        $parser = new ArgumentsParser(M::mock(Filesystem::class)->shouldIgnoreMissing(), (new GitHost\Registry())->registerDefaultHosts());
        $options = ['vendor/name' => ['enable-foo' => true], 'other' => ['with-bar' => '/usr']];
        self::assertSame(['enable-foo' => true], $parser->parseArgument('vendor/name:^1', $options)->getOptions());
        self::assertSame([], $parser->parseArgument('vendor/another', $options)->getOptions());
    }
}
