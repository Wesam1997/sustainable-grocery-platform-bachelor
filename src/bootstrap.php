<?php
declare(strict_types=1);

require_once __DIR__ . '/Config/catalog.php';
spl_autoload_register(static function (string $class): void {
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $map[$file->getBasename('.php')] = $file->getPathname();
            }
        }
    }
    if (isset($map[$class])) require_once $map[$class];
});
require_once __DIR__ . '/../Api/Climate/ClimateServiceFactory.php';

function flashfood_application(mysqli $connection): Application
{
    static $applications = [];
    $key = spl_object_id($connection);
    return $applications[$key] ??= new Application($connection);
}

