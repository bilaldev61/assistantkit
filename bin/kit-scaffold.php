<?php

/**
 * Turns a small db spec into the one port no tool can finish by itself.
 *
 *   php bin/kit-scaffold.php scaffold/catalogue.example.json
 *       # prints the generated Catalogue, LeadSink and the wiring, to the console
 *   php bin/kit-scaffold.php scaffold/catalogue.example.json --out=scaffold
 *       # writes <Name>Catalogue.php + <Name>Leads.php into scaffold/
 *
 * Two of the four ports need no generation at all — AssistantKit\Pdo\PdoStore
 * (chat memory) and AssistantKit\Pdo\PdoLeads (leads) work on any PDO handle and
 * create their own tables via migrate(). This writes the Catalogue from your
 * columns, and a LeadSink only if leads live in your own table. Facts you did
 * not mark answerable are left as comments, so the assistant defers instead of
 * guessing — the same safe-default rule the whole Kit runs on.
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../tools/PortScaffolder.php';

$root = dirname(__DIR__);
$args = array_slice($argv ?? [], 1);

$option = static function (string $name, ?string $default = null) use ($args): ?string {
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            return substr($arg, strlen($name) + 3);
        }
    }

    return $default;
};

$positional = static function (int $index) use ($args): ?string {
    $found = 0;

    foreach ($args as $arg) {
        if (str_starts_with($arg, '--')) {
            continue;
        }

        if ($found === $index) {
            return $arg;
        }

        $found++;
    }

    return null;
};

$pathOf = static function (string $path) use ($root): string {
    return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1
        ? $path
        : $root . '/' . ltrim($path, '/');
};

$help = static function (): void {
    fwrite(STDOUT, <<<TEXT
    Usage:
      php bin/kit-scaffold.php scaffold/<site>.json [--out=scaffold]

      Reads a db spec (table, columns, criteria, facts, optional leads) and writes:
        <Name>Catalogue.php   a PDO-backed Catalogue; unmarked facts stay comments
        <Name>Leads.php       a LeadSink over your own table (only if db.leads set)

      PdoStore and PdoLeads are already usable — this also prints how to bind all four.

    TEXT);
};

$source = $positional(0);

if ($source === null || $source === 'help' || ! is_file($pathOf($source))) {
    $help();
    exit($source === null || $source === 'help' ? 0 : 1);
}

$spec = json_decode((string) file_get_contents($pathOf($source)), true);

if (! is_array($spec)) {
    fwrite(STDERR, "Could not parse JSON: {$source}\n");
    exit(1);
}

try {
    $generated = PortScaffolder::fromSpec($spec);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

$out = $option('out');

if ($out === null) {
    fwrite(STDOUT, "// === {$generated['classes']['catalogue']}.php ===\n\n");
    fwrite(STDOUT, $generated['catalogue'] . "\n");
    fwrite(STDOUT, "\n// === {$generated['classes']['leads']}.php ===\n\n");
    fwrite(STDOUT, $generated['leads'] . "\n");
    fwrite(STDOUT, "\n// === wiring ===\n\n");
    fwrite(STDOUT, $generated['wiring'] . "\n");
    fwrite(STDOUT, "\n(Re-run with --out=scaffold to write the files.)\n");
    exit(0);
}

$dir = $pathOf($out);

if (! is_dir($dir) && ! mkdir($dir, 0777, true) && ! is_dir($dir)) {
    fwrite(STDERR, "Cannot create output directory: {$out}\n");
    exit(1);
}

$files = [
    $generated['classes']['catalogue'] . '.php' => $generated['catalogue'],
    $generated['classes']['leads'] . '.php' => $generated['leads'],
    'wiring.php' => $generated['wiring'],
];

foreach ($files as $name => $contents) {
    file_put_contents($dir . '/' . $name, $contents);
    fwrite(STDOUT, "wrote {$out}/{$name}\n");
}

fwrite(STDOUT, "\nNext: migrate() the chat tables, then finish the fact TODOs you marked.\n");
fwrite(STDOUT, "  (new AssistantKit\\Pdo\\PdoStore(\$pdo))->migrate();\n");
