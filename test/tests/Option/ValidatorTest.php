<?php

namespace IPE\Test\Option;

use IPE\Exception;
use IPE\Option\ConfigureOption;
use IPE\Option\CustomOption;
use IPE\Option\Validator;
use IPE\Test\Support\TestCase;

final class ValidatorTest extends TestCase
{
    /**
     * @return \IPE\Option[]
     */
    private static function getDeclaredOptions()
    {
        return [
            new ConfigureOption('enable-debug', 'Debug', false),
            new ConfigureOption('with-dir', 'Dir', true),
            new ConfigureOption('enable-compression', 'Compression', null, 'no'),
            new CustomOption('variant', 'Variant', true, 'std'),
            new CustomOption('keep', 'Keep', false),
            // Duplicated name: the first one wins
            new ConfigureOption('enable-debug', 'Ignored', true),
        ];
    }

    /**
     * @return list<array{array<string, string|true>, list<string>, array<string, string|true>}>
     */
    public static function provideValidCases()
    {
        return [
            [
                [],
                ['--enable-compression=no'],
                ['variant' => 'std'],
            ],
            [
                ['enable-debug' => true, 'with-dir' => '/usr', 'enable-compression' => 'yes', 'variant' => 'alt', 'keep' => true],
                ['--enable-debug', '--with-dir=/usr', '--enable-compression=yes'],
                ['variant' => 'alt', 'keep' => true],
            ],
            [
                // Leading dashes are accepted, flags may receive a value (e.g. --enable-foo=shared)
                ['--enable-debug' => 'shared', '-enable-compression' => true],
                ['--enable-debug=shared', '--enable-compression'],
                ['variant' => 'std'],
            ],
        ];
    }

    /**
     * @dataProvider provideValidCases
     *
     * @param array<string, string|true> $userOptions
     * @param list<string> $expectedConfigureArguments
     * @param array<string, string|true> $expectedCustomValues
     *
     * @return void
     */
    public function testValid(array $userOptions, array $expectedConfigureArguments, array $expectedCustomValues)
    {
        $values = (new Validator(new \IPE\Logger()))->validate($userOptions, self::getDeclaredOptions(), false, 'the extension foo');
        self::assertSame($expectedConfigureArguments, $values->getConfigureArguments());
        self::assertSame($expectedCustomValues, $values->getCustomValues());
        self::assertSame($expectedConfigureArguments === [] && $expectedCustomValues === [], $values->isEmpty());
        foreach ($expectedCustomValues as $name => $value) {
            self::assertTrue($values->hasCustomValue($name));
            self::assertSame($value, $values->getCustomValue($name));
        }
        self::assertFalse($values->hasCustomValue('nope'));
        self::assertSame('x', $values->getCustomValue('nope', 'x'));
    }

    /**
     * @return list<array{array<string, string|true>, string}>
     */
    public static function provideInvalidCases()
    {
        return [
            [['nope' => true], "does not accept the option 'nope'"],
            [['nope' => true], 'enable-debug'],
            [['with-dir' => true], 'requires a value'],
            [['variant' => true], 'requires a value'],
            [['' => true], 'Invalid option name'],
            [['bad name' => true], 'Invalid option name'],
        ];
    }

    /**
     * @dataProvider provideInvalidCases
     *
     * @param array<string, string|true> $userOptions
     * @param string $expectedMessageFragment
     *
     * @return void
     */
    public function testInvalid(array $userOptions, $expectedMessageFragment)
    {
        try {
            (new Validator(new \IPE\Logger()))->validate($userOptions, self::getDeclaredOptions(), false, 'the extension foo');
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidOptionValues $x) {
            self::assertStringContainsString('the extension foo', $x->getMessage());
            self::assertStringContainsString($expectedMessageFragment, $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testUndeclaredOptions()
    {
        $validator = new Validator(new \IPE\Logger());
        $values = $validator->validate(['with-foo' => '/usr', 'enable-bar' => true], [], true, 'the unknown extension');
        self::assertSame(['--with-foo=/usr', '--enable-bar'], $values->getConfigureArguments());
        self::assertSame([], $values->getCustomValues());
        // Declared options still have precedence
        $values = $validator->validate(['with-foo' => '/usr', 'variant' => 'alt'], self::getDeclaredOptions(), true, 'the unknown extension');
        self::assertSame(['--enable-compression=no', '--with-foo=/usr'], $values->getConfigureArguments());
        // The arguments of IPE win over the defaults, the ones of the user win over them
        self::assertSame(['--enable-compression', '--with-foo=/usr'], $values->withConfigureArgumentsBefore(['--enable-compression'])->getConfigureArguments());
        self::assertSame(['--enable-compression=no', '--with-foo=/usr'], $values->withConfigureArgumentsBefore(['--with-foo=/opt'])->getConfigureArguments());
        self::assertSame(['variant' => 'alt'], $values->getCustomValues());
        try {
            $validator->validate(['nope' => true], [], false, 'the extension foo');
            self::fail('Expected exception not thrown');
        } catch (Exception\InvalidOptionValues $x) {
            self::assertStringContainsString('no options are accepted', $x->getMessage());
        }
    }

    /**
     * @return void
     */
    public function testAutodetectDefault()
    {
        $options = [new ConfigureOption('with-foo', 'Foo', null, 'autodetect'), new ConfigureOption('enable-bar', 'Bar', null, 'autodetect')];
        $values = (new Validator(new \IPE\Logger()))->validate([], $options, false, 'the extension foo');
        self::assertSame(['--with-foo', '--enable-bar=autodetect'], $values->getConfigureArguments());
    }

    /**
     * @return void
     */
    public function testAutodetectValue()
    {
        $options = [new ConfigureOption('with-foo', 'Foo', true), new ConfigureOption('enable-bar', 'Bar', true)];
        $values = (new Validator(new \IPE\Logger()))->validate(['with-foo' => 'autodetect', 'enable-bar' => 'autodetect', 'with-baz' => 'autodetect'], $options, true, 'the extension foo');
        self::assertSame(['--with-foo', '--enable-bar=autodetect', '--with-baz'], $values->getConfigureArguments());
    }
}
