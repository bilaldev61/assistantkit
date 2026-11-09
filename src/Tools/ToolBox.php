<?php

namespace AssistantKit\Tools;

use AssistantKit\Contracts\Catalogue;
use AssistantKit\Contracts\ConversationStore;
use AssistantKit\Contracts\LeadSink;
use AssistantKit\Pack\RulesPack;
use AssistantKit\Value\Conversation;

/**
 * The model's entire menu: the four built-in calls plus whatever the host site
 * registered, trimmed to the tools this site's pack declares.
 *
 * Nothing on this menu is decided by the model. Advertising a tool, running it,
 * and refusing it all happen here, so a tool the site never declared is not
 * merely unused - it is absent from the prompt and unwakeable.
 */
final class ToolBox
{
    private const CORE = ['search_items', 'get_item', 'save_lead', 'record_unanswered_question'];

    private int $recorded = 0;

    private ToolRegistry $registry;

    /** @var list<string> */
    private array $menu;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly Catalogue $catalogue,
        private readonly LeadSink $leads,
        private readonly ConversationStore $store,
        private readonly RulesPack $pack,
        private readonly array $config,
        ?ToolRegistry $tools = null,
    ) {
        // Built-ins first: a site cannot shadow one with a looser handler.
        $this->registry = new ToolRegistry(...$this->builtins());

        foreach ($tools?->all() ?? [] as $tool) {
            if (! $this->registry->has($tool->name)) {
                $this->registry->register($tool);
            }
        }

        $this->menu = $this->resolveMenu();
    }

    /**
     * The list sent to the model. `$readOnly` keeps the writers out for a
     * consumer that is drafting text rather than chatting with a visitor.
     *
     * @return list<array<string, mixed>>
     */
    public function definitions(bool $readOnly = false): array
    {
        $definitions = [];

        foreach ($this->menu as $name) {
            $tool = $this->registry->get($name);

            if ($tool === null || ! $this->permitted($tool)) {
                continue;
            }

            if ($readOnly && $tool->writes) {
                continue;
            }

            $definitions[] = $tool->definition();
        }

        return $definitions;
    }

    /** @return list<string> */
    public function menu(): array
    {
        return array_values(array_filter($this->menu, fn(string $name): bool => $this->permitted($this->registry->get($name))));
    }

    /** @param array<string, mixed> $input */
    public function run(string $name, array $input, Conversation $conversation): mixed
    {
        $tool = $this->registry->get($name);

        if ($tool === null) {
            throw new ToolError('That tool does not exist on this site: ' . $name);
        }

        if (! in_array($name, $this->menu, true)) {
            throw new ToolError('That tool is not enabled for this site: ' . $name);
        }

        if (! $this->permitted($tool)) {
            throw new ToolError('That tool needs a signed-in visitor.');
        }

        return $tool->call($input, [
            'conversation' => $conversation,
            'visitor'      => (array) ($this->config['visitor'] ?? []),
        ]);
    }

    /** True when this turn filed a question, so the reply can be made to offer a way back. */
    public function recordedUnanswered(): bool
    {
        return $this->recorded > 0;
    }

    /**
     * The pack says what the trade can do, the site's config may only trim it,
     * and the registry must actually hold a handler. One intersection, no union.
     *
     * @return list<string>
     */
    private function resolveMenu(): array
    {
        $declared = $this->pack->tools();
        $override = $this->config['tools'] ?? null;

        $names = is_array($override)
            ? array_values(array_intersect($declared, array_map('strval', $override)))
            : $declared;

        // Fail closed: a name with no handler behind it is dropped, not offered.
        return array_values(array_filter(
            array_values(array_unique($names)),
            fn(string $name): bool => $this->registry->has($name),
        ));
    }

    private function permitted(?Tool $tool): bool
    {
        if ($tool === null) {
            return false;
        }

        return ! $tool->requiresAuth || (bool) ($this->config['visitor']['signed_in'] ?? false);
    }

    /** @return list<Tool> */
    private function builtins(): array
    {
        $nounMany = $this->pack->noun('many');

        return [
            new Tool(
                'search_items',
                'Find ' . $nounMany . ' that match what the visitor described. Returns the total and up to a handful of matches.',
                [
                    'type'       => 'object',
                    'properties' => [
                        'area'      => ['type' => 'string', 'description' => 'Area or neighbourhood, written as the visitor said it.'],
                        'beds'      => ['type' => 'integer', 'description' => 'Number of bedrooms. 0 means studio.'],
                        'price_min' => ['type' => 'integer', 'description' => 'Lowest price in the catalogue currency.'],
                        'price_max' => ['type' => 'integer', 'description' => 'Highest price in the catalogue currency.'],
                        'deal'      => ['type' => 'string', 'enum' => ['rent', 'sale'], 'description' => 'Renting or buying.'],
                        'type'      => ['type' => 'string', 'description' => 'Item type, for example Apartment or Villa.'],
                    ],
                    'required'   => [],
                ],
                fn(array $input): array => $this->search($input),
            ),
            new Tool(
                'get_item',
                'Read one item in full by its slug.',
                [
                    'type'       => 'object',
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'The slug from search_items.'],
                    ],
                    'required'   => ['slug'],
                ],
                fn(array $input): array => $this->get($input),
            ),
            new Tool(
                'save_lead',
                'Save the visitor\'s contact details. Call it once the visitor has given a name and an email, or a phone number.',
                [
                    'type'       => 'object',
                    'properties' => [
                        'name'    => ['type' => 'string'],
                        'email'   => ['type' => 'string', 'description' => 'Required unless a phone number was given.'],
                        'phone'   => ['type' => 'string'],
                        'message' => ['type' => 'string', 'description' => 'What they are asking for, in their own words.'],
                    ],
                    'required'   => [],
                ],
                fn(array $input, array $context): array => $this->saveLead($input, $context['conversation']),
                writes: true,
            ),
            new Tool(
                'record_unanswered_question',
                'File a question you could not answer, so the team can add it. Always say so to the visitor and ask for their name and email in the same reply.',
                [
                    'type'       => 'object',
                    'properties' => [
                        'question' => ['type' => 'string', 'description' => 'The visitor\'s question, close to their own wording.'],
                    ],
                    'required'   => ['question'],
                ],
                fn(array $input, array $context): array => $this->record($input, $context['conversation']),
                writes: true,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function search(array $input): array
    {
        $criteria = [];

        foreach (['area', 'type', 'deal'] as $key) {
            if (isset($input[$key]) && is_string($input[$key]) && $input[$key] !== '') {
                $criteria[$key] = $input[$key];
            }
        }

        foreach (['beds', 'price_min', 'price_max'] as $key) {
            if (isset($input[$key]) && is_numeric($input[$key])) {
                $criteria[$key] = (int) $input[$key];
            }
        }

        $result = $this->catalogue->search($criteria, (int) ($this->config['limits']['results'] ?? 6));

        return [
            'total' => (int) ($result['total'] ?? 0),
            'items' => array_map(fn(array $item): array => [
                'slug'    => (string) ($item['slug'] ?? ''),
                'title'   => (string) ($item['title'] ?? ''),
                'summary' => (string) ($item['summary'] ?? $item['price_label'] ?? ''),
                'url'     => (string) ($item['url'] ?? ''),
            ], (array) ($result['items'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function get(array $input): array
    {
        $slug = trim((string) ($input['slug'] ?? ''));

        if ($slug === '') {
            throw new ToolError('A slug is required.');
        }

        $item = $this->catalogue->find($slug);

        if ($item === null) {
            throw new ToolError('No item found with the slug ' . $slug . '.');
        }

        return [
            'slug'  => $slug,
            'title' => (string) ($item['title'] ?? ''),
            'url'   => (string) ($item['url'] ?? ''),
            'facts' => $this->catalogue->facts($item),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function saveLead(array $input, Conversation $conversation): array
    {
        if ($conversation->isLead()) {
            return ['saved' => false, 'note' => 'This visitor is already a lead; do not ask again.'];
        }

        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ToolError('That email address does not look valid; ask the visitor to check it.');
        }

        if ($email === '' && $phone === '') {
            throw new ToolError('An email or a phone number is required.');
        }

        $conversation->leadRef = $this->leads->capture([
            'name'    => trim((string) ($input['name'] ?? '')) ?: null,
            'email'   => $email,
            'phone'   => $phone !== '' ? $phone : null,
            'message' => trim((string) ($input['message'] ?? '')) ?: null,
            'item'    => $conversation->itemSlug,
            'source'  => 'chat',
            'chat'    => $conversation->id,
        ]);

        return ['saved' => true, 'lead_ref' => $conversation->leadRef];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function record(array $input, Conversation $conversation): array
    {
        $question = trim((string) ($input['question'] ?? ''));

        if ($question === '') {
            throw new ToolError('A question is required.');
        }

        $asked = $this->store->questions($conversation);
        $limit = (int) ($this->config['limits']['questions_per_chat'] ?? 5);

        if (count($asked) >= $limit) {
            return ['recorded' => false, 'note' => 'The question log for this chat is full; stop filing and ask for an email instead.'];
        }

        foreach ($asked as $entry) {
            if (mb_strtolower(trim((string) $entry['question'])) === mb_strtolower($question)) {
                return ['recorded' => false, 'note' => 'That question is already filed.'];
            }
        }

        $this->store->recordQuestion($conversation, $question, $conversation->itemSlug);
        $this->recorded++;

        return ['recorded' => true];
    }
}
