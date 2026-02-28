<?php

use LaravelZero\Framework\Application;

if (! isset($_SERVER['HOME']) && ($home = getenv('HOME'))) {
    $_SERVER['HOME'] = $home;
}
if (! isset($_SERVER['HOME'])) {
    $_SERVER['HOME'] = '/home/oracle';
}

return Application::configure(basePath: dirname(__DIR__))->create();
