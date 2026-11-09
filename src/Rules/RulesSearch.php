<?php

namespace AssistantKit\Rules;

use AssistantKit\Contracts\Catalogue;
use AssistantKit\Pack\RulesPack;
use AssistantKit\Support\Text;
use AssistantKit\Value\Conversation;
use AssistantKit\Value\Reply;

/**
 * The listing layer: the same regex idea, but the answers come from the host's
 * catalogue. Returns null when the message is not about finding something, so
 * the model still gets judgement questions.
 */
final class RulesSearch
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly Catalogue $catalogue,
        private readonly RulesPack $pack,
        private readonly array $config,
    ) {}

    public function answer(Conversation $conversation, string $message): ?Reply
    {
        $text = Text::normalise($message);

        if ($text === '') {
            return null;
        }

        if ($reply = $this->process($text)) {
            return $reply;
        }

        $criteria = $this->criteria($text);

        if ($criteria === [] && ! $this->pack->matches('search', $text)) {
            return null;
        }

        $limit = (int) ($this->config['limits']['results'] ?? 6);
        $result = $this->catalogue->search($criteria, $limit);
        $total = (int) ($result['total'] ?? 0);

        if ($total === 0) {
            // Nothing was actually filtered out, so the message was not a search:
            // let the model or the team answer instead of "nothing matching".
            if ($criteria === []) {
                return null;
            }

            return new Reply($this->none($conversation, $criteria), 'rule:search_none');
        }

        return new Reply($this->listing($result, $criteria), 'rule:search');
    }

    /**
     * "How does renting work" is answered from config, never from a search:
     * procedural questions contain no digits, and amounts are left to the team.
     */
    private function process(string $text): ?Reply
    {
        if (Text::hasDigit($text)) {
            return null;
        }

        if (preg_match('/\b(how|what|steps?|process|procedure|works?|need|require|documents?)\b/', $text) !== 1) {
            return null;
        }

        foreach ($this->pack->process() as $topic => $pattern) {
            if (preg_match($pattern, $text) !== 1) {
                continue;
            }

            $process = $this->config['process'][$topic] ?? null;

            if (is_array($process)) {
                return new Reply($this->render($process), 'rule:process');
            }
        }

        return null;
    }

    /** @param array<string, mixed> $process */
    private function render(array $process): string
    {
        $lines = [(string) ($process['title'] ?? 'How it works')];

        foreach ((array) ($process['steps'] ?? []) as $index => $step) {
            $lines[] = ($index + 1) . '. ' . $step;
        }

        if (isset($process['note'])) {
            $lines[] = (string) $process['note'];
        }

        return implode("\n", $lines);
    }

    /** @return array<string, mixed> */
    private function criteria(string $text): array
    {
        $criteria = [];

        $beds = $this->pack->dot('criteria.beds');

        if (is_string($beds) && preg_match($beds, $text, $match) === 1) {
            $criteria['beds'] = isset($match[2]) && $match[2] !== '' ? 0 : (int) $match[1];
        }

        foreach (['min', 'max'] as $bound) {
            $pattern = $this->pack->dot('criteria.price.' . $bound);

            if (is_string($pattern) && preg_match($pattern, $text, $match) === 1) {
                $criteria['price_' . $bound] = $this->money($match[1], $match[2] ?? '');
            }
        }

        $rent = $this->pack->dot('criteria.rent');
        $sale = $this->pack->dot('criteria.sale');

        if (is_string($rent) && preg_match($rent, $text) === 1) {
            $criteria['deal'] = 'rent';
        } elseif (is_string($sale) && preg_match($sale, $text) === 1) {
            $criteria['deal'] = 'sale';
        }

        $type = $this->pack->dot('criteria.type');

        if (is_string($type) && preg_match($type, $text, $match) === 1) {
            $types = $this->pack->types();
            $criteria['type'] = $types[mb_strtolower($match[1])] ?? Text::sentence($match[1]);
        }

        if ($area = $this->area($text)) {
            $criteria['area'] = $area;
        }

        return $criteria;
    }

    private function area(string $text): ?string
    {
        foreach ($this->pack->aliases() as $alias => $area) {
            if (preg_match('/\b' . preg_quote((string) $alias, '/') . '\b/', $text) === 1) {
                return (string) $area;
            }
        }

        foreach ($this->pack->areas() as $area) {
            if (str_contains($text, Text::normalise((string) $area))) {
                return (string) $area;
            }
        }

        // An unknown place is not a criterion: "at night", "in writing" and
        // "in terms of" all look like an area to a regex, and none of them are.
        return null;
    }

    private function money(string $number, string $unit): int
    {
        $value = (float) str_replace([',', ' '], '', $number);
        $unit = mb_strtolower($unit);

        if (in_array($unit, ['k', 'thousand'], true)) {
            $value *= 1000;
        } elseif (in_array($unit, ['m', 'million'], true)) {
            $value *= 1_000_000;
        }

        return (int) round($value);
    }

    /**
     * @param  array{total?: int, items?: list<array<string, mixed>>}  $result
     * @param  array<string, mixed>  $criteria
     */
    private function listing(array $result, array $criteria): string
    {
        $items = (array) ($result['items'] ?? []);
        $total = (int) ($result['total'] ?? count($items));
        $noun = $total === 1 ? $this->pack->noun('one') : $this->pack->noun('many');
        $described = $this->describe($criteria);

        $lines = ['I have ' . $total . ' ' . $noun . ($described !== '' ? ' matching ' . $described : '') . ':'];

        foreach ($items as $item) {
            $lines[] = '- ' . $item['title'] . ($this->summary($item) !== null ? ' - ' . $this->summary($item) : '');
        }

        if ($total > count($items)) {
            $lines[] = '...and ' . ($total - count($items)) . ' more on the site.';
        }

        $lines[] = 'Tell me which one you like and I will send the details.';

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $criteria */
    private function none(Conversation $conversation, array $criteria): string
    {
        $described = $this->describe($criteria);

        $text = 'I have nothing ' . ($described !== '' ? 'matching ' . $described : 'for that') . ' right now. ';

        return $text . ($conversation->isLead()
            ? 'I have your details and ' . $this->brand('agent') . ' will send you what comes up.'
            : $this->ask());
    }

    /**
     * "2 bedroom apartment to rent in Dubai Marina" - one phrase, reused for the
     * found and the empty case so both read like a person wrote them.
     *
     * @param array<string, mixed> $criteria
     */
    private function describe(array $criteria): string
    {
        $parts = [];
        $beds = array_key_exists('beds', $criteria)
            ? ($criteria['beds'] === 0 ? 'studio' : (int) $criteria['beds'] . ' bedroom')
            : null;
        $type = isset($criteria['type']) ? mb_strtolower((string) $criteria['type']) : null;

        if ($type !== null && $type === $beds) {
            $type = null;                                   // "studio studio"
        }

        $what = trim(($beds ?? '') . ' ' . ($type ?? ''));

        if ($what !== '') {
            $parts[] = $what;
        }

        if (isset($criteria['deal'])) {
            $parts[] = $criteria['deal'] === 'rent' ? 'to rent' : 'for sale';
        }

        if (isset($criteria['area'])) {
            $parts[] = 'in ' . $criteria['area'];
        }

        if (isset($criteria['price_max'])) {
            $parts[] = 'under ' . number_format((int) $criteria['price_max']);
        }

        if (isset($criteria['price_min'])) {
            $parts[] = 'over ' . number_format((int) $criteria['price_min']);
        }

        return implode(' ', $parts);
    }

    /** @param array<string, mixed> $item */
    private function summary(array $item): ?string
    {
        foreach (['summary', 'price_label', 'subtitle'] as $key) {
            if (isset($item[$key]) && is_string($item[$key]) && $item[$key] !== '') {
                return $item[$key];
            }
        }

        return null;
    }

    private function ask(): string
    {
        $asks = (array) ($this->config['asks'] ?? []);
        $template = (string) ($asks['details'] ?? 'Leave your name and email here and :agent will come back to you.');

        return Text::fill($template, ['agent' => $this->brand('agent'), 'brand' => $this->brand('name')]);
    }

    private function brand(string $key): string
    {
        $brand = (array) ($this->config['brand'] ?? []);

        return (string) ($brand[$key] ?? ($key === 'agent' ? 'the team' : ''));
    }
}
