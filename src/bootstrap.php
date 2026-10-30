<?php
declare(strict_types=1);
require_once __DIR__ . '/Config/catalog.php';

/**
 * Automatically load application classes.
 */
spl_autoload_register(static function (string $class): void {
    static $map = null;

    if ($map === null) {
        $map = [];

        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(__DIR__)
            ) as $file
        ) {
            if (
                $file->isFile() &&
                $file->getExtension() === 'php'
            ) {
                $map[$file->getBasename('.php')] =
                    $file->getPathname();
            }
        }
    }

    if (isset($map[$class])) {
        require_once $map[$class];
    }
});

require_once __DIR__ . '/Modules/Climate/ClimateServiceFactory.php';

/**
 * Application composition root.
 *
 * Climate calculations remain deterministic in PHP.
 * Ollama is optional and used only for explanations.
 */
function flashfood_application(mysqli $connection): Application
{
    static $applications = [];

    $key = spl_object_id($connection);

    if (!isset($applications[$key])) {

        // Ollama is disabled by default.
        // Enable it using FLASHFOOD_ENABLE_OLLAMA=1.
        $enableOllama = getenv('FLASHFOOD_ENABLE_OLLAMA') === '1';

        $llm = $enableOllama
            ? new OllamaLlmClient()
            : null;

        $applications[$key] = new Application(
            $connection,
            $llm
        );
    }

    return $applications[$key];
}
