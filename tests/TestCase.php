<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $application = parent::createApplication();
        $connection = $application['config']->get('database.default');
        $database = $application['config']->get("database.connections.{$connection}");

        if ($connection === 'sqlite' && ($database['database'] ?? null) === ':memory:') {
            return $application;
        }

        if (in_array($connection, ['mysql', 'mariadb'], true)
            && preg_match('/^shield_test(?:_[a-z0-9_]+)?$/D', $database['database'] ?? '')
            && in_array($database['host'] ?? null, ['localhost', '127.0.0.1', '::1'], true)
            && empty($database['url'])) {
            return $application;
        }

        throw new \RuntimeException('Tests require memory SQLite or an isolated local shield_test database.');
    }
}
