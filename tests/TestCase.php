<?php

namespace WPCustomTables\Tests;

use PHPUnit\Framework;
use Brain\Monkey;

class TestCase extends Framework\TestCase
{

    protected function setUp()
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown()
    {
        Monkey\tearDown();
        parent::tearDown();
    }
}
