<?php

namespace IPE\Test\CLI;

use IPE\CLI\ArgumentsParser;
use IPE\Extension\Asked;
use IPE\Filesystem;
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
        $parser = new ArgumentsParser($fs);

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
     * @return void
     */
    public function testComposerOptions()
    {
        $parser = new ArgumentsParser(M::mock(Filesystem::class)->shouldIgnoreMissing());
        $options = ['vendor/name' => ['enable-foo' => true], 'other' => ['with-bar' => '/usr']];
        self::assertSame(['enable-foo' => true], $parser->parseArgument('vendor/name:^1', $options)->getOptions());
        self::assertSame([], $parser->parseArgument('vendor/another', $options)->getOptions());
    }
}
