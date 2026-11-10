<?php

/**
 * Drafts a rules table so a new site starts from words it already knows,
 * not a blank page. Two feeds, both pure functions, no model and no database:
 *
 *   fromInventory(spec)   the columns/labels your site can answer  -> facts rows
 *   fromQuestions(list)   the real questions visitors asked         -> clustered rows
 *
 * The output is the SAME shape rules/<niche>.rules.php is written by hand, so it
 * feeds straight into bin/rules-build.php -> lint -> cases. A draft never claims a
 * fact is answerable unless the inventory said so: with no `fact`, the row only
 * defers, which is the safe default when you have not checked your data yet.
 */
final class RulesDrafter
{
    /** Words that carry no topic, so a cluster is never named after one. */
    private const STOPWORDS = [
        'a',
        'an',
        'the',
        'and',
        'or',
        'but',
        'to',
        'of',
        'in',
        'on',
        'at',
        'for',
        'with',
        'is',
        'are',
        'was',
        'were',
        'be',
        'been',
        'do',
        'does',
        'did',
        'can',
        'could',
        'would',
        'should',
        'will',
        'i',
        'you',
        'we',
        'they',
        'it',
        'its',
        'my',
        'your',
        'our',
        'me',
        'us',
        'this',
        'that',
        'these',
        'those',
        'there',
        'here',
        'what',
        'which',
        'who',
        'how',
        'when',
        'where',
        'why',
        'if',
        'so',
        'please',
        'hi',
        'hello',
        'hey',
        'thanks',
        'thank',
        'have',
        'has',
        'had',
        'any',
        'some',
        'about',
    ];

    /** Phrases a rule needs even when the inventory forgets them. */
    private const DEFAULT_TOOLS = ['search_items', 'get_item', 'save_lead', 'record_unanswered_question'];

    // ──────────────────────────────────────────────────────────── inventory

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>  a rules table ready for rules-build.php
     */
    public static function fromInventory(array $spec): array
    {
        $name = self::slug((string) ($spec['name'] ?? 'draft'));
        $nouns = (array) ($spec['nouns'] ?? []);

        $table = [
            'name'  => $name !== '' ? $name : 'draft',
            'tools' => array_values((array) ($spec['tools'] ?? self::DEFAULT_TOOLS)),
            'nouns' => [
                'one'   => (string) ($nouns['one'] ?? 'item'),
                'many'  => (string) ($nouns['many'] ?? 'items'),
                'thing' => (string) ($nouns['thing'] ?? 'record'),
            ],
        ];

        $types = (array) ($spec['types'] ?? []);

        if ($types !== []) {
            $table['types'] = array_map('strval', $types);
        }

        $areas = array_values(array_filter(array_map('strval', (array) ($spec['areas'] ?? []))));

        $criteria = ['price' => self::priceCriteria()];

        if ($areas !== []) {
            $table['areas'] = $areas;
            $criteria['area'] = self::areaRegex();
        }

        $table['criteria'] = $criteria;

        $facts = [];

        foreach ((array) ($spec['facts'] ?? []) as $row) {
            $row = (array) $row;
            $key = self::slug((string) ($row['key'] ?? $row['column'] ?? ''));

            if ($key === '') {
                continue;
            }

            $phrases = self::phrases($row['asks'] ?? $row['phrases'] ?? [], $key);

            if ($phrases === []) {
                continue;
            }

            $entry = ['key' => $key, 'phrases' => $phrases];

            // A `fact` is only wired when the inventory says the column answers it.
            // Anything else is an honest deferral, never a guessed value.
            if (isset($row['fact']) && (string) $row['fact'] !== '') {
                $entry['fact'] = (string) $row['fact'];
            }

            $entry['defer'] = (string) ($row['defer'] ?? 'the ' . str_replace('_', ' ', $key));

            $facts[] = $entry;
        }

        if ($facts !== []) {
            $table['facts'] = $facts;
        }

        $process = [];

        foreach ((array) ($spec['process'] ?? []) as $topic => $asks) {
            $phrases = self::phrases((array) $asks, (string) $topic);

            if ($phrases !== []) {
                $process[self::slug((string) $topic)] = $phrases;
            }
        }

        if ($process !== []) {
            $table['process'] = $process;
        }

        // Generic-but-correct defaults so a drafted pack greets, deflects judgement
        // and answers "what are your hours?" out of the box. Review and narrow them.
        $table['agency'] = (array) ($spec['agency'] ?? self::defaultAgency());
        $table['intents'] = (array) ($spec['intents'] ?? self::defaultIntents());

        return $table;
    }

    // ──────────────────────────────────────────────────────────── question log

    /**
     * Group the questions visitors actually asked into topics, so the next rules
     * come from demand instead of imagination.
     *
     * @param  list<string>  $questions
     * @return list<array{key: string, phrases: list<string>, count: int, asks: list<string>}>
     */
    public static function cluster(array $questions): array
    {
        /** @var array<int, array{tokens: array<string, true>, asks: list<string>}> $groups */
        $groups = [];
        $owner = [];   // token -> group index, so shared words merge groups
        $nextId = 0;   // merges free keys, so ids must never be reused

        foreach ($questions as $question) {
            $text = self::cleanQuestion((string) $question);
            $tokens = self::salient($text);

            if ($text === '' || $tokens === []) {
                continue;
            }

            $targets = array_values(array_unique(array_filter(
                array_map(fn(string $t): ?int => $owner[$t] ?? null, $tokens),
                static fn(?int $index): bool => $index !== null,
            )));

            if ($targets === []) {
                $index = $nextId++;
                $groups[$index] = ['tokens' => [], 'asks' => []];

                foreach ($tokens as $token) {
                    $owner[$token] = $index;
                }
            } else {
                $index = $targets[0];

                foreach ($targets as $merge) {
                    if ($merge === $index) {
                        continue;
                    }

                    $groups[$index]['asks'] = array_merge($groups[$index]['asks'], $groups[$merge]['asks']);

                    foreach (array_keys($groups[$merge]['tokens']) as $token) {
                        $owner[$token] = $index;
                    }

                    unset($groups[$merge]);
                }
            }

            $groups[$index]['asks'][] = $text;

            foreach ($tokens as $token) {
                $groups[$index]['tokens'][$token] = true;
            }
        }

        $clusters = [];

        foreach ($groups as $group) {
            if ($group['asks'] === []) {
                continue;
            }

            $counts = [];

            foreach ($group['asks'] as $ask) {
                $counts[$ask] = ($counts[$ask] ?? 0) + 1;
            }

            arsort($counts);
            $asks = array_keys($counts);
            $key = self::topToken(array_keys($group['tokens']), $group['asks']);

            $clusters[] = [
                'key'     => $key,
                'phrases' => self::phrases($asks, $key),
                'count'   => count($group['asks']),
                'asks'    => array_slice($asks, 0, 5),
            ];
        }

        usort($clusters, fn(array $a, array $b): int => [$b['count'], $a['key']] <=> [$a['count'], $b['key']]);

        return $clusters;
    }

    /**
     * Turn a question log into a report and a draft table of only the topics your
     * current pack cannot already answer.
     *
     * @param  list<string>      $questions
     * @param  RulesInspector|null  $existing  when given, clusters it matches are "covered"
     * @return array{table: array<string, mixed>, covered: list<array{ask: string, rule: string}>, clusters: int}
     */
    public static function fromQuestions(array $questions, ?object $existing = null, string $name = 'draft'): array
    {
        $clusters = self::cluster($questions);
        $rows = [];
        $covered = [];

        foreach ($clusters as $cluster) {
            $sample = $cluster['asks'][0] ?? $cluster['key'];

            $rule = $existing === null ? null : $existing->firstFactRule($sample);

            if ($rule !== null) {
                $covered[] = ['ask' => $sample, 'rule' => (string) $rule['key']];
                continue;
            }

            // A logged question proves the wording, not the data: draft it as a
            // deferral so it never invents a fact the site has not confirmed.
            $rows[] = [
                'key'     => $cluster['key'],
                'phrases' => $cluster['phrases'],
                'defer'   => 'the ' . str_replace('_', ' ', $cluster['key']),
            ];
        }

        $table = [
            'name'  => self::slug($name) !== '' ? self::slug($name) : 'draft',
            'facts' => $rows,
        ];

        return ['table' => $table, 'covered' => $covered, 'clusters' => count($clusters)];
    }

    // ──────────────────────────────────────────────────────────── render

    /** @param array<string, mixed> $table */
    public static function render(array $table, string $source = 'bin/rules-draft.php'): string
    {
        return "<?php\n\n"
            . "/**\n"
            . " * Drafted by bin/rules-draft.php from {$source}.\n"
            . " * This is a starting vocabulary, not a finished pack: wire each 'fact'\n"
            . " * to a real Catalogue::facts() key, then build and lint:\n"
            . " *   php bin/rules-build.php rules/" . ($table['name'] ?? 'draft') . ".rules.php --out=packs/" . ($table['name'] ?? 'draft') . ".php\n"
            . " */\n\n"
            . 'return ' . self::export($table, 0) . ";\n";
    }

    // ──────────────────────────────────────────────────────────── helpers

    private static function areaRegex(): string
    {
        return '/\b(in|at|near|around|close to)\s+([a-z][a-z\s]{2,30})/i';
    }

    /** @return array{min: string, max: string} */
    private static function priceCriteria(): array
    {
        return [
            'min' => '/\b(?:from|over|above|at least)\s*(?:aed\s*)?([\d.,]+)\s*(k|m|million|thousand)?/i',
            'max' => '/\b(?:under|below|up to|max|budget of)\s*(?:aed\s*)?([\d.,]+)\s*(k|m|million|thousand)?/i',
        ];
    }

    /** @return array<string, string> */
    private static function defaultAgency(): array
    {
        return [
            'contact' => '/\b(phone|call|contact|whatsapp|reach you|speak to)\b/i',
            'email'   => '/\b(e-?mail)\b/i',
            'hours'   => '/\b(hours|open|opening|timings?|closed|weekend)\b/i',
            'office'  => '/\b(office|address|located|location|where are you|based)\b/i',
            'areas'   => '/\b(which areas|what areas|areas do you|cover)\b/i',
        ];
    }

    /** @return array<string, string> */
    private static function defaultIntents(): array
    {
        return [
            'greeting'  => '/^(hi|hey|hello|good (morning|afternoon|evening))\b/i',
            'thanks'    => '/\b(thanks|thank you|appreciate it|got it)\b/i',
            'search'    => '/\b(looking for|do you have|show me|options|available|list)\b/i',
            'judgement' => '/\b(compare|better|best|recommend|should i|why|worth it)\b/i',
        ];
    }

    /**
     * The phrases a rule matches on: the asks the site supplied, cleaned and
     * deduped, with the topic key kept as a broad catch-all so the rule is never
     * narrower than the word it is named after.
     *
     * @return list<string>
     */
    private static function phrases(mixed $asks, string $key): array
    {
        $out = [];

        foreach ((array) $asks as $ask) {
            $ask = self::cleanQuestion((string) $ask);

            if ($ask !== '' && ! in_array($ask, $out, true)) {
                $out[] = $ask;
            }
        }

        $words = self::salient($key);

        foreach ($words as $word) {
            if (! in_array($word, $out, true)) {
                $out[] = $word;
            }
        }

        return array_slice($out, 0, 8);
    }

    private static function cleanQuestion(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = (string) preg_replace("/[^\p{L}\p{N} '+\-%? ]/u", ' ', $text);
        $text = (string) preg_replace('/\s+/', ' ', $text);

        return trim($text, ' ?.!;,');
    }

    /**
     * @return list<string> words that name the topic, stopwords and 1-letter noise dropped
     */
    private static function salient(string $text): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}+\-%]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];

        foreach ($tokens as $token) {
            if (mb_strlen($token) < 2 || in_array($token, self::STOPWORDS, true)) {
                continue;
            }

            $out[] = $token;
        }

        return array_values(array_unique($out));
    }

    /**
     * The word that best labels a cluster: the salient token that appears in the
     * most member questions, ties broken alphabetically so output is stable.
     *
     * @param  list<string>  $tokens
     * @param  list<string>  $asks
     */
    private static function topToken(array $tokens, array $asks): string
    {
        $score = [];

        foreach ($tokens as $token) {
            $hits = 0;

            foreach ($asks as $ask) {
                if (str_contains($ask, $token)) {
                    $hits++;
                }
            }

            $score[$token] = $hits;
        }

        if ($score === []) {
            return 'topic';
        }

        arsort($score);
        $best = array_key_first($score);

        foreach ($score as $token => $hits) {
            if ($hits === $score[$best] && $token < $best) {
                $best = $token;
            }
        }

        return self::slug((string) $best);
    }

    private static function slug(string $text): string
    {
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', '_', mb_strtolower(trim($text)));

        return trim($text, '_');
    }

    private static function export(mixed $value, int $depth): string
    {
        if (! is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        $pad = str_repeat('    ', $depth);
        $list = array_is_list($value);
        $lines = [];

        foreach ($value as $key => $item) {
            $prefix = $list ? '' : var_export((string) $key, true) . ' => ';
            $lines[] = $pad . '    ' . $prefix . self::export($item, $depth + 1) . ',';
        }

        return "[\n" . implode("\n", $lines) . "\n" . $pad . ']';
    }
}
