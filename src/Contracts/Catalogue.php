<?php

namespace AssistantKit\Contracts;

/**
 * The only thing the engine knows about your data. Implement it once per site
 * (about forty lines) and the assistant works for any catalogue.
 */
interface Catalogue
{
    /**
     * @param  array<string, mixed>  $criteria  beds, price_min, price_max, deal (rent|sale), type, area
     * @return array{total: int, items: list<array<string, mixed>>}
     *
     * Every item must carry `slug`, `title` and `url`. Anything else is
     * optional and reaches the visitor only through facts().
     */
    public function search(array $criteria, int $limit): array;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array;

    /**
     * Ready-made sentences about one item, keyed the way the rules pack names
     * them (`price`, `bedrooms`, `availability`, ...). This is the one place
     * facts are formatted, and the model's only source of truth.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    public function facts(array $item): array;
}
