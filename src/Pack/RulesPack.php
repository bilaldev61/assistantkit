<?php

namespace AssistantKit\Pack;

/**
 * One industry's vocabulary, loaded from a PHP file in packs/. Nothing in the
 * engine names a property, a car or a treatment: it asks the pack.
 */
final class RulesPack
{
    /** @param array<string, mixed> $data */
    public function __construct(private readonly array $data) {}

    public static function load(string $path): self
    {
        $data = require $path;

        if (! is_array($data)) {
            throw new \InvalidArgumentException('A rules pack must return an array: ' . $path);
        }

        return new self($data);
    }

    public function name(): string
    {
        return (string) $this->dot('name', 'default');
    }

    /**
     * What tools this trade declares. An allowlist: a tool added to the engine
     * later is not inherited by packs written before it existed.
     *
     * @return list<string>
     */
    public function tools(): array
    {
        $tools = $this->dot('tools', null);

        if (! is_array($tools)) {
            return ['search_items', 'get_item', 'save_lead', 'record_unanswered_question'];
        }

        return array_values(array_filter(array_map('strval', $tools), fn(string $name): bool => $name !== ''));
    }

    /** @return list<string> */
    public function areas(): array
    {
        return array_values((array) $this->dot('areas', []));
    }

    /** @return array<string, string> */
    public function aliases(): array
    {
        return (array) $this->dot('aliases', []);
    }

    /** @return array<string, array<string, string>> */
    public function facts(): array
    {
        return (array) $this->dot('facts', []);
    }

    /** @return array<string, string> */
    public function process(): array
    {
        return (array) $this->dot('process', []);
    }

    /** @return array<string, string> */
    public function agency(): array
    {
        return (array) $this->dot('agency', []);
    }

    /** @return array<string, string> */
    public function intents(): array
    {
        return (array) $this->dot('intents', []);
    }

    /** @return array<string, string> */
    public function types(): array
    {
        return (array) $this->dot('types', []);
    }

    public function noun(string $form = 'many'): string
    {
        return (string) $this->dot('nouns.' . $form, $form === 'many' ? 'items' : 'item');
    }

    public function matches(string $intent, string $text): bool
    {
        $pattern = $this->dot('intents.' . $intent);

        return is_string($pattern) && preg_match($pattern, $text) === 1;
    }

    public function dot(string $key, mixed $default = null): mixed
    {
        $value = $this->data;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
