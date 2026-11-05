<?php

namespace PHPSTORM_META;

die('Access Denied.');

// $app = \IPE\createApp(); $app->make('something');
override(\IPE\Service\Container::make(0), map([
    '' => '@',
]));

// $app = \IPE\createApp(); $app->build(SomeClass::class);
override(\IPE\Service\Container::build(0), map([
    '' => '@',
]));
