<?php

/**
 * Ensure gitignored env files exist for PHPUnit / fresh clones.
 *
 * Only copies from tracked examples when the target file is missing.
 * Never overwrites an existing .env or .env.testing.
 */
function ensure_local_env_files(string $projectRoot): void
{
    $ensure = static function (string $target, string $example) use ($projectRoot): void {
        $targetPath = $projectRoot.DIRECTORY_SEPARATOR.$target;
        $examplePath = $projectRoot.DIRECTORY_SEPARATOR.$example;

        if (is_file($targetPath) || ! is_file($examplePath)) {
            return;
        }

        copy($examplePath, $targetPath);
    };

    $ensure('.env', '.env.example');
    $ensure('.env.testing', '.env.testing.example');
}
