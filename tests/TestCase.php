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
    private array $protectedDatabases = [
        'hrms_new',
        'hrms',
        'hrmscrm',
        'salescrm',
        'salescrm_live',
        'sales_crm_live_2',
        'salecrm_live_2',
        'salecrm2',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstProtectedDatabase();
        $this->guardAgainstProtectedSalesCrmDatabase();
    }

    /**
     * Runs before migrate:fresh — must block before any wipe.
     */
    protected function beforeRefreshingDatabase()
    {
        $this->guardAgainstProtectedDatabase();
        $this->guardAgainstProtectedSalesCrmDatabase();
    }

    protected function guardAgainstProtectedDatabase(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (in_array($database, $this->protectedDatabases, true)) {
            throw new RuntimeException(
                "Refusing to run tests against protected database [{$connection}/{$database}]. ".
                'Use hrms_testing / salescrm_testing — never production CRM or HRMS databases.'
            );
        }
    }

    protected function guardAgainstProtectedSalesCrmDatabase(): void
    {
        $database = (string) config('database.connections.salescrm.database');

        if (in_array($database, $this->protectedDatabases, true)) {
            throw new RuntimeException(
                "Refusing to run tests against protected Sales CRM database [{$database}]. ".
                'Set SALES_DB_DATABASE=salescrm_testing in phpunit.xml.'
            );
        }

        // Extra hard stop: Sales CRM connection must be an explicit *_testing database.
        if ($database === '' || ! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "Refusing to run tests: salescrm connection must use a '*_testing' database, got [{$database}]."
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
