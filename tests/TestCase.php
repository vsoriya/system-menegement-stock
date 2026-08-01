<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Env;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run unless the target database is obviously a throwaway one.
     *
     * RefreshDatabase drops every table before the suite runs. If the test
     * configuration ever pointed at the live database, a single `composer test`
     * would erase a shop's entire trading history with no warning and no way
     * back. That is the worst thing this project could do to someone, so it is
     * guarded rather than trusted.
     *
     * The check runs before parent::setUp(), because that is what boots the
     * application and triggers the refresh. Checking afterwards would be too
     * late: the tables would already be gone.
     */
    protected function setUp(): void
    {
        $this->guardAgainstNonTestDatabase();

        parent::setUp();
    }

    /**
     * Read straight from the environment rather than config(), since the
     * application is not booted yet. PHPUnit's own <env> entries are already in
     * place at this point, so this sees exactly what the suite will connect to.
     */
    protected function guardAgainstNonTestDatabase(): void
    {
        $connection = (string) Env::get('DB_CONNECTION', 'mysql');
        $database = (string) Env::get('DB_DATABASE', '');

        // In-memory SQLite is disposable by definition, and a blank name means
        // no database is configured at all.
        if ($database === '' || $database === ':memory:') {
            return;
        }

        // A live database will never be named this way, and the suite's own
        // database always is.
        if (str_ends_with($database, '_test')) {
            return;
        }

        throw new RuntimeException(
            "Refusing to run the test suite against the database [{$database}] on connection ".
            "[{$connection}], because its name does not end in _test and the suite drops every ".
            'table before it runs. Check the DB_DATABASE value in phpunit.xml.'
        );
    }
}
