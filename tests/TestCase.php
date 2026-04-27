<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Suppress PHP 8.5 vendor deprecation noise from PDO::MYSQL_ATTR_SSL_CA
        error_reporting(error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        parent::setUp();
    }
}
