<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

passthru(sprintf(
    'APP_ENV=test php "%s/../bin/console" doctrine:database:create --if-not-exists --quiet 2>&1',
    __DIR__
));

passthru(sprintf(
    'APP_ENV=test php "%s/../bin/console" doctrine:migrations:migrate --no-interaction --quiet 2>&1',
    __DIR__
));
