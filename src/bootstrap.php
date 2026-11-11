<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require __DIR__ . '/helpers.php';

App\App::init(require APP_ROOT . '/config.php');
