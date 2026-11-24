<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();
        $database = $connection->getDatabaseName();

        if (! ($connection->getDriverName() === 'sqlite' && $database === ':memory:')
            && ! preg_match('/^nexus[_-]erp[_-].*[_-]tests?$/', (string) $database)) {
            throw new RuntimeException("Pruebas canceladas: la base '{$database}' no es una base aislada de pruebas.");
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token');
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
