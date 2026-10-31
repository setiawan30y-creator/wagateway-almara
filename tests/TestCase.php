<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        \App\Support\Tenancy::forget();
        parent::setUp();
        // jam kerja siang agar hasil tes tidak bergantung pada jam tenang (21:00–08:00)
        $this->travelTo(now()->setTime(12, 0));
    }

    protected function tearDown(): void
    {
        \App\Support\Tenancy::forget();
        parent::tearDown();
    }
}
