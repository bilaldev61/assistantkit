<?php

/**
 * Drafts a rules table so a new site never starts from a blank page.
 *
 *   php bin/rules-draft.php inventory rules/inventory.example.json
 *       # your answerable columns -> a rules table for rules-build.php
 *   php bin/rules-draft.php questions rules/questions.example.txt --pack=property
 *       # the questions visitors asked -> clustered rules, minus what you cover
 *
 * Both print a table you can read; --out writes it to rules/. Nothing here builds
 * a pack or touches a model: the output feeds the pipeline you already trust —
 *   php bin/rules-build.php rules/<name>.rules.php --out=packs/<name>.php
 *   php bin/rules.php lint --pack=<name>
 *   php bin/rules.php cases
 */

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../tools/RulesInspector.php';
require __DIR__ . '/../tools/RulesDrafter.php';

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

$pathOf = static function (string $path) use ($root): string {
    return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1
        ? $path
        : $root . '/' . ltrim($path, '/');
};

if ($command === 'inventory') {
    $source = $positional(1);

    if ($source === null || ! is_file($pathOf($source))) {
        fwrite(STDERR, "Usage: php bin/rules-draft.php inventory rules/<site>.json [--out=rules/<site>.rules.php]\n");
        exit(1);
    }

    $spec = json_decode((string) file_get_contents($pathOf($source)), true);

    if (! is_array($spec)) {
        fwrite(STDERR, "An inventory must be a JSON object: {$source}\n");
        exit(1);
    }

    $table = RulesDrafter::fromInventory($spec);
    $code = RulesDrafter::render($table, $source);
    $facts = count((array) ($table['facts'] ?? []));

    $out = $option('out');

    if ($out !== null) {
        $target = $pathOf($out);
        file_put_contents($target, $code);
        echo 'Drafted ' . $facts . ' fact rule(s) to ' . $out . ".\n";
        echo "Next: php bin/rules-build.php {$out} --out=packs/" . basename($out, '.rules.php') . ".php\n";
        exit(0);
    }

    echo $code;
    exit(0);
}

if ($command === 'questions') {
    $source = $positional(1);

    if ($source === null || ! is_file($pathOf($source))) {
        fwrite(STDERR, "Usage: php bin/rules-draft.php questions <log.txt|log.json> [--pack=name] [--out=rules/<site>.draft.php]\n");
        exit(1);
    }

    $questions = readQuestions($pathOf($source));

    if ($questions === []) {
        fwrite(STDERR, "No questions found in {$source} (one per line, or a JSON list).\n");
        exit(1);
    }

    $configPath = $pathOf($option('config') ?? 'config/assistant.php');
    $config = is_file($configPath) ? require $configPath : [];
    $packName = $option('pack') ?? (string) ($config['pack'] ?? 'property');
    $packPath = $pathOf('packs/' . $packName . '.php');

    $existing = is_file($packPath) ? RulesInspector::fromPaths($packPath, $configPath) : null;
    $draft = RulesDrafter::fromQuestions($questions, $existing, $option('name') ?? $packName);

    echo 'Questions read: ' . count($questions) . '  ·  topics found: ' . $draft['clusters'] . "\n\n";

    if ($draft['covered'] !== []) {
        echo "Already answered by pack '{$packName}' (" . count($draft['covered']) . "):\n";

        foreach ($draft['covered'] as $row) {
            echo '  · ' . $row['ask'] . '  -> rule:' . $row['rule'] . "\n";
        }

        echo "\n";
    }

    $newRules = (array) ($draft['table']['facts'] ?? []);

    if ($newRules === []) {
        echo "Nothing new to draft — every topic is already covered.\n";
    } else {
        echo 'New topics to add (' . count($newRules) . "), drafted as deferrals until you wire a fact:\n";

        foreach ($newRules as $row) {
            echo '  · ' . $row['key'] . '  [' . implode(', ', array_slice($row['phrases'], 0, 4)) . "]\n";
        }
    }

    $out = $option('out');

    if ($out !== null && $newRules !== []) {
        $target = $pathOf($out);
        file_put_contents($target, RulesDrafter::render($draft['table'], $source));
        echo "\nWrote " . $out . ". These are drafted as deferrals only:\n";
        echo "  1. move the topics you want into rules/<name>.rules.php, give each a real\n";
        echo "     'fact' once Catalogue::facts() can supply it (leave the rest to defer);\n";
        echo "  2. php bin/rules-build.php rules/<name>.rules.php --out=packs/<name>.php;\n";
        echo "  3. php bin/rules.php lint --pack=<name>  and  php bin/rules.php cases.\n";
    } elseif ($out !== null) {
        echo "\nNo --out written (no new topics).\n";
    }

    exit(0);
}

echo <<<TXT
    php bin/rules-draft.php inventory rules/<site>.json [--out=rules/<site>.rules.php]
        your answerable columns become a rules table (facts you mark answerable, deferrals you don't)

    php bin/rules-draft.php questions <log> [--pack=name] [--out=rules/<site>.draft.php]
        cluster the questions visitors asked; report which your pack already answers and draft the rest

        <log> is one question per line, or JSON: ["..."] / [{"question":"..."}] / {"questions":["..."]}

    Then feed any drafted table through the pipeline you already trust:
        php bin/rules-build.php rules/<name>.rules.php --out=packs/<name>.php
        php bin/rules.php lint --pack=<name>
        php bin/rules.php cases

TXT;
exit(0);

/**
 * @return list<string>
 */
function readQuestions(string $path): array
{
    $raw = (string) file_get_contents($path);
    $json = json_decode($raw, true);

    if (is_array($json)) {
        $list = $json['questions'] ?? $json;
        $out = [];

        foreach ((array) $list as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = $item;
            } elseif (is_array($item) && isset($item['question']) && is_string($item['question'])) {
                $out[] = $item['question'];
            }
        }

        return $out;
    }

    $out = [];

    foreach (preg_split('/\R/', $raw) ?: [] as $line) {
        $line = trim($line);

        if ($line !== '' && ! str_starts_with($line, '#')) {
            $out[] = $line;
        }
    }

    return $out;
}
