<?php

/**
 * Builds a pack from a plain-language rules table.
 *
 *   php bin/rules-build.php rules/bikes.rules.php --out=packs/bikes.php
 *   php bin/rules-build.php rules/bikes.rules.php            # print instead
 *
 * The table lists words; this writes the regexes, escapes them, keeps your row
 * order as the priority, and refuses to build a table where a broader rule sits
 * above a narrower one.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../tools/RulesCompiler.php';

$root = dirname(__DIR__);
$args = array_slice($argv ?? [], 1);

$sourceRead = null;
$out = null;
$force = false;

foreach ($args as $arg) {
    if ($arg === '--force') {
        $force = true;
    } elseif (str_starts_with($arg, '--out=')) {
        $out = substr($arg, strlen('--out='));
    } elseif (! str_starts_with($arg, '--')) {
        $sourceRead ??= $arg;
    }
}

if ($sourceRead === null) {
    fwrite(STDERR, "Usage: php bin/rules-build.php rules/<pack>.rules.php [--out=packs/<pack>.php] [--force]\n");
    exit(1);
}

$sourcePath = str_starts_with($sourceRead, '/') || preg_match('/^[A-Za-z]:/', $sourceRead) === 1
    ? $sourceRead
    : $root . '/' . $sourceRead;

if (! is_file($sourcePath)) {
    fwrite(STDERR, "No rules table at {$sourcePath}\n");
    exit(1);
}

$table = require $sourcePath;

if (! is_array($table)) {
    fwrite(STDERR, "A rules table must return an array: {$sourcePath}\n");
    exit(1);
}

$relative = static function (string $path) use ($root): string {
    return str_replace(str_replace('\\', '/', $root) . '/', '', str_replace('\\', '/', $path));
};

try {
    $code = RulesCompiler::compile($table, $relative($sourcePath), $force);
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

if ($out !== null) {
    $target = str_starts_with($out, '/') || preg_match('/^[A-Za-z]:/', $out) === 1
        ? $out
        : $root . '/' . $out;

    file_put_contents($target, $code);
    echo 'Wrote ' . $relative($target) . ' (' . substr_count($code, "\n") . " lines).\n";
    echo "Check it: php bin/rules.php lint --pack=" . basename($target, '.php') . "\n";
    exit(0);
}

echo $code;
exit(0);
