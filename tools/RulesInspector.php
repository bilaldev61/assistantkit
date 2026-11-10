<?php

use AssistantKit\Assistant;
use AssistantKit\Demo\FakeCatalogue;
use AssistantKit\Demo\MemoryLeadSink;
use AssistantKit\Demo\MemoryStore;
use AssistantKit\Pack\RulesPack;
use AssistantKit\Support\Text;

/**
 * Reads a pack the way the engine reads it and reports what is wrong, plus which
 * rule answers a given sentence. Development tool only: the engine never calls
 * this file.
 */
final class RulesInspector
{
    private const BUILTIN_TOOLS = ['search_items', 'get_item', 'save_lead', 'record_unanswered_question'];

    private const AGENCY_KEYS = ['contact', 'email', 'hours', 'office', 'areas'];

    private const INTENT_KEYS = ['greeting', 'thanks', 'identity', 'search', 'judgement'];

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly RulesPack $pack,
        private readonly array $config,
    ) {}

    public static function fromPaths(string $packPath, string $configPath): self
    {
        $config = require $configPath;

        return new self(RulesPack::load($packPath), is_array($config) ? $config : []);
    }

    /**
     * @return list<array{level: string, message: string}>
     */
    public function lint(): array
    {
        $out = [];

        foreach ($this->patterns() as $label => $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                $out[] = ['error', $label . ' has no pattern'];
                continue;
            }

            if (@preg_match($pattern, 'test') === false) {
                $out[] = ['error', $label . ' is not a valid regex: ' . $pattern];
            }
        }

        $out = array_merge($out, $this->lintShape());

        if ($this->pack->name() === '') {
            $out[] = ['warning', "the pack has no 'name'"];
        }

        $expected = (string) ($this->config['pack'] ?? '');

        if ($expected !== '' && $expected !== $this->pack->name()) {
            $out[] = ['warning', "config loads pack '{$expected}' but this file calls itself '{$this->pack->name()}'"];
        }

        return $this->normaliseFindings($out);
    }

    /**
     * Findings are written both as [level, message] and as a keyed array; the
     * callers only ever see one shape.
     *
     * @param  list<array<int|string, string>>  $findings
     * @return list<array{level: string, message: string}>
     */
    private function normaliseFindings(array $findings): array
    {
        return array_map(
            static fn(array $finding): array => isset($finding['level'])
                ? ['level' => (string) $finding['level'], 'message' => (string) $finding['message']]
                : ['level' => (string) $finding[0], 'message' => (string) $finding[1]],
            $findings,
        );
    }

    /**
     * The first fact rule whose pattern matches, in pack order. This mirrors
     * QuickAnswers::fact() so a "wrong answer" can be traced to one line.
     *
     * @return array{key: string, fact: ?string, defer: ?string}|null
     */
    public function firstFactRule(string $message): ?array
    {
        $text = Text::normalise($message);

        foreach ($this->pack->facts() as $key => $rule) {
            $pattern = $rule['match'] ?? null;

            if (! is_string($pattern) || @preg_match($pattern, $text) !== 1) {
                continue;
            }

            return [
                'key'   => (string) $key,
                'fact'  => isset($rule['fact']) ? (string) $rule['fact'] : null,
                'defer' => isset($rule['defer']) ? (string) $rule['defer'] : null,
            ];
        }

        return null;
    }

    /**
     * Runs one message through the real engine, rules only: no model, no
     * database, fresh chat each time.
     *
     * @return array{kind: string, text: string}
     */
    public function probe(string $message, ?string $itemSlug = null): array
    {
        $assistant = new Assistant(
            new FakeCatalogue(),
            new MemoryStore(),
            new MemoryLeadSink(),
            $this->pack,
            $this->config,
            null,
        );

        $reply = $assistant->reply('probe', $message, $itemSlug);

        return ['kind' => $reply->kind, 'text' => $reply->text];
    }

    /** @return array<string, string> */
    private function patterns(): array
    {
        $patterns = [];
        $criteria = (array) $this->pack->dot('criteria', []);

        foreach ($criteria as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $bound => $pattern) {
                    $patterns['criteria.' . $name . '.' . $bound] = $pattern;
                }
                continue;
            }

            $patterns['criteria.' . $name] = $value;
        }

        foreach ($this->pack->facts() as $key => $rule) {
            if (is_array($rule) && array_key_exists('match', $rule)) {
                $patterns['facts.' . $key . '.match'] = $rule['match'];
            }
        }

        foreach ($this->pack->process() as $topic => $pattern) {
            $patterns['process.' . $topic] = $pattern;
        }

        foreach ($this->pack->agency() as $topic => $pattern) {
            $patterns['agency.' . $topic] = $pattern;
        }

        foreach ($this->pack->intents() as $name => $pattern) {
            $patterns['intents.' . $name] = $pattern;
        }

        return $patterns;
    }

    /** @return list<array{level: string, message: string}> */
    private function lintShape(): array
    {
        $out = [];

        foreach (['one', 'many'] as $form) {
            if ($this->pack->noun($form) === '') {
                $out[] = ['warning', "nouns.{$form} is empty, so generated sentences will read oddly"];
            }
        }

        foreach ($this->pack->types() as $word => $label) {
            if ($word !== mb_strtolower((string) $word)) {
                $out[] = ['warning', "types key '{$word}' is not lowercase: the engine looks up the lowercased word, so this entry never matches"];
            }

            if (! is_string($label) || $label === '') {
                $out[] = ['error', "types.{$word} has no label"];
            }
        }

        $price = $this->pack->dot('criteria.price');

        if (is_string($price)) {
            $out[] = ['error', "criteria.price is a string: the engine expects ['min' => ..., 'max' => ...]"];
        }

        if ($this->pack->areas() === []) {
            $out[] = ['warning', 'areas is empty: no place in a message can ever become a filter'];
        }

        foreach ($this->pack->aliases() as $alias => $area) {
            if ($alias !== mb_strtolower((string) $alias)) {
                $out[] = ['warning', "alias '{$alias}' is not lowercase: messages are normalised, so it never matches"];
            }

            if (! in_array($area, $this->pack->areas(), true)) {
                $out[] = ['warning', "alias '{$alias}' points at '{$area}', which is not in areas"];
            }
        }

        if (empty($this->pack->facts())) {
            $out[] = ['warning', 'facts is empty: only greetings, agency answers, searches and fallbacks can answer'];
        }

        foreach ($this->pack->facts() as $key => $rule) {
            if (! is_array($rule)) {
                $out[] = ['error', "facts.{$key} must be an array with 'match'"];
                continue;
            }

            if (! isset($rule['match'])) {
                $out[] = ['error', "facts.{$key} has no 'match' pattern"];
                continue;
            }

            $hasFact = isset($rule['fact']) && (string) $rule['fact'] !== '';
            $hasDefer = isset($rule['defer']) && (string) $rule['defer'] !== '';

            if (! $hasFact && ! $hasDefer) {
                $out[] = ['error', "facts.{$key} has neither 'fact' nor 'defer': it can only block later rules"];
            } elseif (! $hasDefer) {
                $out[] = ['warning', "facts.{$key} has no 'defer': when the value is missing the question falls through instead of asking for details"];
            }
        }

        foreach ($this->pack->process() as $topic => $pattern) {
            if (! is_array($this->config['process'][$topic] ?? null)) {
                $out[] = ['warning', "process.{$topic} has no steps in config: the message will never be answered from here"];
            }
        }

        foreach ($this->pack->agency() as $topic => $pattern) {
            if (! in_array($topic, self::AGENCY_KEYS, true)) {
                $out[] = ['warning', "agency.{$topic} is not renderable: the engine only handles " . implode(', ', self::AGENCY_KEYS)];
            }
        }

        foreach (self::INTENT_KEYS as $needed) {
            if (! array_key_exists($needed, $this->pack->intents())) {
                $out[] = ['warning', "intents.{$needed} is missing: that behaviour is switched off for this pack"];
            }
        }

        foreach ($this->pack->intents() as $name => $pattern) {
            if (! in_array($name, self::INTENT_KEYS, true)) {
                $out[] = ['warning', "intents.{$name} is never read by the engine"];
            }
        }

        foreach ($this->pack->tools() as $tool) {
            if (! in_array($tool, self::BUILTIN_TOOLS, true)) {
                $out[] = ['warning', "tool '{$tool}' is not a built-in: it stays hidden until a ToolRegistry registers that handler"];
            }
        }

        $brand = (array) ($this->config['brand'] ?? []);

        foreach (['agent', 'phone'] as $needed) {
            if (! isset($brand[$needed]) || (string) $brand[$needed] === '') {
                $out[] = ['warning', "config brand.{$needed} is empty: contact answers will fall back to 'the team'"];
            }
        }

        return $out;
    }
}
