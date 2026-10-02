<?php

namespace Tests\Unit;

use Tests\TestCase;

class TestingBootstrapTest extends TestCase
{
    public function test_ensure_local_env_files_does_not_overwrite_existing_files(): void
    {
        $root = sys_get_temp_dir().'/kafaat-bootstrap-'.uniqid('', true);
        mkdir($root);

        copy(base_path('.env.example'), $root.'/.env.example');
        copy(base_path('.env.testing.example'), $root.'/.env.testing.example');

        file_put_contents($root.'/.env', "CUSTOM_ENV_MARKER=keep\nAPP_KEY=base64:existing\n");
        file_put_contents($root.'/.env.testing', "CUSTOM_TESTING_MARKER=keep\nDB_CONNECTION=sqlite\n");

        require_once base_path('tests/support/ensure_local_env_files.php');
        ensure_local_env_files($root);

        $this->assertSame("CUSTOM_ENV_MARKER=keep\nAPP_KEY=base64:existing\n", file_get_contents($root.'/.env'));
        $this->assertSame("CUSTOM_TESTING_MARKER=keep\nDB_CONNECTION=sqlite\n", file_get_contents($root.'/.env.testing'));

        @unlink($root.'/.env');
        @unlink($root.'/.env.testing');
        @unlink($root.'/.env.example');
        @unlink($root.'/.env.testing.example');
        @rmdir($root);
    }

    public function test_ensure_local_env_files_creates_missing_files_from_examples(): void
    {
        $root = sys_get_temp_dir().'/kafaat-bootstrap-'.uniqid('', true);
        mkdir($root);

        copy(base_path('.env.example'), $root.'/.env.example');
        copy(base_path('.env.testing.example'), $root.'/.env.testing.example');

        require_once base_path('tests/support/ensure_local_env_files.php');
        ensure_local_env_files($root);

        $this->assertFileExists($root.'/.env');
        $this->assertFileExists($root.'/.env.testing');
        $this->assertStringContainsString('APP_NAME=', file_get_contents($root.'/.env'));
        $this->assertStringContainsString('DB_CONNECTION=', file_get_contents($root.'/.env.testing'));

        @unlink($root.'/.env');
        @unlink($root.'/.env.testing');
        @unlink($root.'/.env.example');
        @unlink($root.'/.env.testing.example');
        @rmdir($root);
    }

    public function test_phpunit_bootstrap_file_requires_helper_and_autoload(): void
    {
        $this->assertFileExists(base_path('tests/bootstrap.php'));
        $this->assertFileExists(base_path('tests/support/ensure_local_env_files.php'));

        $contents = file_get_contents(base_path('tests/bootstrap.php'));
        $this->assertIsString($contents);
        $this->assertStringContainsString('ensure_local_env_files.php', $contents);
        $this->assertStringContainsString('vendor/autoload.php', $contents);
    }
}
