<?php

require __DIR__.'/support/ensure_local_env_files.php';

$projectRoot = dirname(__DIR__);

ensure_local_env_files($projectRoot);

require $projectRoot.'/vendor/autoload.php';
