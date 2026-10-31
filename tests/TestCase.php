<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        \App\Support\Tenancy::forget();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        \App\Support\Tenancy::forget();
        parent::tearDown();
    }
}
