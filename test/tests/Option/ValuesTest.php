<?php

namespace IPE\Test\Option;

use IPE\Option\Values;
use IPE\Test\Support\TestCase;

final class ValuesTest extends TestCase
{
    /**
     * @return array<array{list<non-empty-string>, list<non-empty-string>}>
     */
    public static function provideRepeatedConfigureArguments()
    {
        return [
            [['--enable-foo', '--with-bar'], ['--enable-foo', '--with-bar']],
            [['--enable-foo=no', '--with-bar', '--enable-foo'], ['--with-bar', '--enable-foo']],
            [['--enable-foo', '--disable-foo'], ['--disable-foo']],
            [['--disable-foo', '--enable-foo=yes'], ['--enable-foo=yes']],
            [['--with-foo=/usr', '--without-foo'], ['--without-foo']],
            [['--without-foo', '--with-foo=/opt'], ['--with-foo=/opt']],
            [['--enable-foo-bar', '--enable-foo_bar=no', '--disable-foo.bar'], ['--disable-foo.bar']],
            [['--with-foo+bar', '--without-foo-bar'], ['--without-foo-bar']],
            // enable and with options are stored in different variables
            [['--enable-foo', '--with-foo'], ['--enable-foo', '--with-foo']],
            [['--enable-foo', '--enable-foobar'], ['--enable-foo', '--enable-foobar']],
            // Other arguments are left untouched
            [['--build=a', '--enable-foo', '--build=b', '--build=b', '--disable-foo'], ['--build=a', '--build=b', '--build=b', '--disable-foo']],
            [['--enable-foo=a=b', '--enable-foo=a=c'], ['--enable-foo=a=c']],
        ];
    }

    /**
     * @dataProvider provideRepeatedConfigureArguments
     *
     * @param list<non-empty-string> $arguments
     * @param list<non-empty-string> $expected
     *
     * @return void
     */
    public function testRepeatedConfigureArguments(array $arguments, array $expected)
    {
        self::assertSame($expected, (new Values($arguments))->getConfigureArguments());
    }

    /**
     * @return void
     */
    public function testConfigureArgumentsPrecedence()
    {
        $values = new Values(['--enable-user=yes', '--enable-both=user'], [], ['--enable-default=no', '--enable-user=no', '--enable-both=no', '--enable-ipe=no']);
        self::assertSame(
            ['--enable-default=no', '--enable-ipe=yes', '--enable-user=yes', '--enable-both=user'],
            $values->withConfigureArgumentsBefore(['--enable-ipe=yes', '--enable-both=ipe'])->getConfigureArguments()
        );
    }

    /**
     * @return array<array{list<non-empty-string>, non-empty-string, string|null}>
     */
    public static function provideConfigureValues()
    {
        return [
            [[], 'enable-foo', null],
            [['--enable-foo'], 'enable-foo', 'yes'],
            [['--enable-foo'], 'disable-foo', 'yes'],
            [['--enable-foo=no'], 'enable-foo', 'no'],
            [['--disable-foo'], 'enable-foo', 'no'],
            [['--with-foo=/usr'], 'with-foo', '/usr'],
            [['--without-foo'], 'with-foo', 'no'],
            [['--with-foo=/usr', '--without-foo', '--with-foo=a=b'], 'with-foo', 'a=b'],
            [['--with-foo-bar'], 'with-foo_bar', 'yes'],
            // enable and with options are stored in different variables
            [['--with-foo'], 'enable-foo', null],
            [['--enable-foobar'], 'enable-foo', null],
            [['--build=foo'], 'build', null],
        ];
    }

    /**
     * @dataProvider provideConfigureValues
     *
     * @param list<non-empty-string> $arguments
     * @param non-empty-string $name
     * @param string|null $expected
     *
     * @return void
     */
    public function testConfigureValues(array $arguments, $name, $expected)
    {
        self::assertSame($expected, (new Values($arguments))->getConfigureValue($name));
    }

    /**
     * @return void
     */
    public function testDefaultsAreNotConfigureValues()
    {
        self::assertNull((new Values([], [], ['--enable-foo=no']))->getConfigureValue('enable-foo'));
    }
}
