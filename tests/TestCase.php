<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages live in modules; InertiaPagesExistTest checks them.
        config(["inertia.testing.ensure_pages_exist" => false]);
    }
}
