IPE installs snuffleupagus with its default rules, which also block IPE itself.
To run IPE again after snuffleupagus has been installed, you have two options:

- run it with the rules disabled: `php -d sp.configuration_file= /usr/local/bin/install-php-extensions ...`
- install snuffleupagus with the `--snuffleupagus:allow-ipe` option.

The `--snuffleupagus:allow-ipe` option adds to the rules some exceptions for the files of IPE, using the path where IPE is when it installs snuffleupagus (if you later run IPE from another path, it will be blocked).
Since snuffleupagus ignores the `function_r()` rules for the functions that have `function()` rules, IPE adds an exception for every function mentioned in the default rules.

Please remark that, with this option, any PHP code can bypass the rules of snuffleupagus by including the files of IPE and using its classes: delete IPE when you no longer need it (or remove the exceptions between `# BEGIN install-php-extensions rules` and `# END install-php-extensions rules` at the beginning of the `snuffleupagus.rules` file).

The option is available only when running IPE as a program (`bin/install-php-extensions` or the phar): when using IPE as a library, it's up to you to handle the rules of snuffleupagus (see [LIBRARY.md](LIBRARY.md#snuffleupagus)).
