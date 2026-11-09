<?php

/**
 * Rule tooling. Three commands, no dependencies, no setup:
 *
 *   php bin/rules.php lint                 # is this pack sane?
 *   php bin/rules.php ask "how much is it?" --item=marina-2br
 *                                          # which rule answers, and what it says
 *   php bin/rules.php cases                # replay rules/<pack>.cases.php
 *
 * Options: --pack=bikes  --path=packs/bikes.php  --config=config/assistant.php
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../tools/RulesInspector.php';
require __DIR__ . '/../tools/CaseRunner.php';

$root = dirname(__DIR__);
$args = array_slice($argv ?? [], 1);
$command = $args[0] ?? 'help';

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

$configPath = $root . '/' . ($option('config') ?? 'config/assistant.php');

if (! is_file($configPath)) {
    fwrite(STDERR, "No config at {$configPath}\n");
    exit(1);
}

$config = require $configPath;
$packName = (string) ($option('pack') ?? ($config['pack'] ?? 'property'));
$packPath = $option('path') ?? $root . '/packs/' . $packName . '.php';

if (! is_file($packPath)) {
    fwrite(STDERR, "No pack at {$packPath}\n");
    exit(1);
}

$inspector = RulesInspector::fromPaths($packPath, $configPath);

if ($command === 'lint') {
    $findings = $inspector->lint();
    $errors = 0;

    echo 'Pack: ' . basename($packPath) . '  (' . count($inspector->lint()) . " findings)\n\n";

    foreach ($findings as $finding) {
        $tag = $finding['level'] === 'error' ? 'ERROR' : 'warn ';
        $errors += $finding['level'] === 'error' ? 1 : 0;
        echo '  ' . $tag . '  ' . $finding['message'] . "\n";
    }

    if ($findings === []) {
        echo "  clean.\n";
    }

    echo "\n" . ($errors > 0 ? "{$errors} error(s) to fix.\n" : "No errors.\n");
    exit($errors > 0 ? 1 : 0);
}

if ($command === 'ask') {
    $message = $positional(1);

    if ($message === null || $message === '') {
        fwrite(STDERR, "Usage: php bin/rules.php ask \"your question\" [--item=slug]\n");
        exit(1);
    }

    $item = $option('item');
    $rule = $inspector->firstFactRule($message);
    $reply = $inspector->probe($message, $item);

    echo '> ' . $message . ($item !== null ? '   [item: ' . $item . ']' : '') . "\n";
    echo '  kind    ' . $reply['kind'] . "\n";
    echo '  facts   ' . ($rule === null
        ? 'no fact rule matched'
        : $rule['key'] . '  (fact: ' . ($rule['fact'] ?? 'none') . ', defer: ' . ($rule['defer'] ?? 'none') . ')') . "\n";
    echo "  reply\n";

    foreach (explode("\n", $reply['text']) as $line) {
        echo '    ' . $line . "\n";
    }

    echo "\n  reply kinds: rule:* answered by your rules, ai* by the model, fallback = nobody could, lead = captured.\n";
    exit(0);
}

if ($command === 'cases') {
    $casesPath = $option('cases') ?? $root . '/rules/' . $packName . '.cases.php';

    if (! is_file($casesPath)) {
        fwrite(STDERR, "No case table at {$casesPath}\n");
        exit(1);
    }

    $loaded = require $casesPath;
    $cases = is_array($loaded['cases'] ?? null) ? $loaded['cases'] : $loaded;

    $results = (new CaseRunner($inspector))->run($cases);
    $failed = 0;

    echo 'Cases: ' . basename($casesPath) . '  (' . count($results) . ")\n\n";

    foreach ($results as $result) {
        $failed += $result['ok'] ? 0 : 1;
        echo '  ' . ($result['ok'] ? 'ok  ' : 'FAIL') . '  ' . $result['ask'] . "\n";

        if (! $result['ok']) {
            echo '        expected ' . $result['expect'] . ', got ' . $result['got'] . "\n";

            foreach (explode("\n", $result['text']) as $line) {
                echo '        | ' . $line . "\n";
            }
        }
    }

    echo "\n" . (count($results) - $failed) . ' passed, ' . $failed . " failed.\n";
    exit($failed > 0 ? 1 : 0);
}

echo <<<TXT
    php bin/rules.php lint                      check the pack for broken or dead rules
    php bin/rules.php ask "question" --item=x   show which rule answers and what it says
    php bin/rules.php cases                     replay the case table in rules/

    Options: --pack=name  --path=file  --config=file  --item=slug  --cases=file

TXT;
exit(0);
