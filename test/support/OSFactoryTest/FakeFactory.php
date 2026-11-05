<?php

namespace IPE\Test\Support\OSFactoryTest;

use IPE\OS\Factory;

final class FakeFactory extends Factory
{
    /**
     * {@inheritdoc}
     *
     * @see \IPE\OS\Factory::getOSClasses()
     */
    protected function getOSClasses()
    {
        return array_merge(parent::getOSClasses(), [FakeOS::class]);
    }
}
