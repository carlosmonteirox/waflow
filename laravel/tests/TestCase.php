<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Rate-limit counters live beyond a database transaction when the
        // production-like Redis cache is used by the PostgreSQL test suite.
        Cache::flush();
        $this->withoutVite();
    }
}
