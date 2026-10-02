<?php

namespace Tests\Unit;

use App\Support\SqliteDatabasePath;
use Tests\TestCase;

class SqliteDatabasePathTest extends TestCase
{
    public function test_bare_database_name_is_kept_inside_the_database_directory(): void
    {
        $resolved = SqliteDatabasePath::resolve('postgres');

        $this->assertSame(database_path('postgres'), $resolved);
        $this->assertNotSame(base_path('postgres'), $resolved);
    }

    public function test_relative_database_directory_path_is_not_nested_twice(): void
    {
        $this->assertSame(
            database_path('database.sqlite'),
            SqliteDatabasePath::resolve('database/database.sqlite'),
        );
    }

    public function test_memory_and_absolute_paths_are_unchanged(): void
    {
        $absolute = database_path('database.sqlite');

        $this->assertSame(':memory:', SqliteDatabasePath::resolve(':memory:'));
        $this->assertSame('file:memdb?mode=memory', SqliteDatabasePath::resolve('file:memdb?mode=memory'));
        $this->assertSame($absolute, SqliteDatabasePath::resolve($absolute));
    }

    public function test_empty_and_path_traversal_fall_back_to_the_default_sqlite_file(): void
    {
        $default = database_path('database.sqlite');

        $this->assertSame($default, SqliteDatabasePath::resolve(null));
        $this->assertSame($default, SqliteDatabasePath::resolve(''));
        $this->assertSame($default, SqliteDatabasePath::resolve('../postgres'));
        $this->assertSame($default, SqliteDatabasePath::resolve('database/../postgres'));
        $this->assertSame($default, SqliteDatabasePath::resolve('file:postgres'));
    }

    public function test_booted_sqlite_connection_stays_in_memory_for_phpunit(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }
}
