<?php

namespace IPE\Test\Support;

abstract class TestCase9 extends TestCaseBase
{
    public final static function setUpBeforeClass(): void
    {
        static::doSetUpBeforeClass();
    }

    public final function setUp(): void
    {
        parent::setUp();
        static::doSetUp();
    }

    public final function tearDown(): void
    {
        static::doTearDown();
        parent::tearDown();
    }

    public final static function tearDownAfterClass(): void
    {
        static::doTearDownAfterClass();
    }
}
