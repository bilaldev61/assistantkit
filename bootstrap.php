<?php

/**
 * Works with or without a composer install:
 *  - with one, vendor/autoload.php handles src/ through the PSR-4 map;
 *  - without one (or when borrowed from another project, as in the demo run),
 *    set ASSISTANTKIT_VENDOR to any autoload.php and the maps below take over.
 */
$vendor = getenv('ASSISTANTKIT_VENDOR') ?: __DIR__ . '/vendor/autoload.php';

if (is_file($vendor)) {
    require $vendor;
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'AssistantKit\\Tests\\' => __DIR__ . '/tests/',
        'AssistantKit\\'        => __DIR__ . '/src/',
    ];

    foreach ($prefixes as $prefix => $root) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $path = $root . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});
