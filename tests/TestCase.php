<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var list<string>
     */
    private array $protectedDatabases = ['hrms_new', 'hrms', 'hrmscrm'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstProtectedDatabase();
    }

    /**
     * Runs before migrate:fresh — must block before any wipe.
     */
    protected function beforeRefreshingDatabase()
    {
        $this->guardAgainstProtectedDatabase();
    }

    protected function guardAgainstProtectedDatabase(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (in_array($database, $this->protectedDatabases, true)) {
            throw new RuntimeException(
                "Refusing to run tests against protected database [{$connection}/{$database}]. ".
                'Enable pdo_sqlite for :memory: tests, or use a dedicated DB like hrms_testing — never hrms_new.'
            );
        }
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
