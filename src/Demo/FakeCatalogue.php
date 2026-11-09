<?php

namespace AssistantKit\Demo;

use AssistantKit\Contracts\Catalogue;

/**
 * Four made-up listings, enough to run the engine and the tests without a
 * database. Copy the shape of facts() when you write the real adapter.
 */
final class FakeCatalogue implements Catalogue
{
    /** @var list<array<string, mixed>> */
    private const ITEMS = [
        [
            'slug'    => 'marina-2br',
            'title'   => '2-bedroom apartment in Dubai Marina',
            'area'    => 'Dubai Marina',
            'beds'    => 2,
            'price'   => 2_100_000,
            'deal'    => 'sale',
            'type'    => 'Apartment',
            'url'     => 'https://example.com/properties/marina-2br',
            'summary' => 'AED 2,100,000 - 2 bed - Dubai Marina',
            'facts'   => [
                'price'        => 'The asking price is AED 2,100,000.',
                'bedrooms'     => 'It is a two-bedroom apartment with a maid\'s room.',
                'size'         => 'It is 1,180 sq ft built-up.',
                'availability' => 'It is vacant and available now.',
                'parking'      => 'It comes with one covered parking space.',
                'furnishing'   => 'It is unfurnished.',
                'handover'     => 'It is ready now.',
            ],
        ],
        [
            'slug'    => 'jvc-studio',
            'title'   => 'Studio in Jumeirah Village Circle',
            'area'    => 'Jumeirah Village Circle',
            'beds'    => 0,
            'price'   => 42_000,
            'deal'    => 'rent',
            'type'    => 'Studio',
            'url'     => 'https://example.com/properties/jvc-studio',
            'summary' => 'AED 42,000 a year - studio - JVC',
            'facts'   => [
                'price'        => 'The rent is AED 42,000 a year, paid in four cheques.',
                'bedrooms'     => 'It is a studio with a separate kitchen.',
                'size'         => 'It is 430 sq ft.',
                'availability' => 'It is free from the first of next month.',
                'furnishing'   => 'It comes furnished.',
            ],
        ],
        [
            'slug'    => 'downtown-3br',
            'title'   => '3-bedroom apartment in Downtown Dubai',
            'area'    => 'Downtown Dubai',
            'beds'    => 3,
            'price'   => 4_800_000,
            'deal'    => 'sale',
            'type'    => 'Apartment',
            'url'     => 'https://example.com/properties/downtown-3br',
            'summary' => 'AED 4,800,000 - 3 bed - Downtown Dubai',
            'facts'   => [
                'price'    => 'The asking price is AED 4,800,000.',
                'bedrooms' => 'It is a three-bedroom apartment.',
                'size'     => 'It is 1,950 sq ft.',
            ],
        ],
        [
            'slug'    => 'marina-villa',
            'title'   => '4-bedroom villa in Dubai Marina',
            'area'    => 'Dubai Marina',
            'beds'    => 4,
            'price'   => 9_500_000,
            'deal'    => 'sale',
            'type'    => 'Villa',
            'url'     => 'https://example.com/properties/marina-villa',
            'summary' => 'AED 9,500,000 - 4 bed - Dubai Marina',
            'facts'   => [
                'price'    => 'The asking price is AED 9,500,000.',
                'bedrooms' => 'It is a four-bedroom villa.',
            ],
        ],
    ];

    /** @var list<array<string, mixed>> */
    private readonly array $items;

    /** @param list<array<string, mixed>>|null $items */
    public function __construct(?array $items = null)
    {
        $this->items = $items ?? self::ITEMS;
    }

    public function search(array $criteria, int $limit): array
    {
        $matches = array_values(array_filter(
            $this->items,
            fn(array $item): bool => $this->matches($item, $criteria),
        ));

        return ['total' => count($matches), 'items' => array_slice($matches, 0, $limit)];
    }

    public function find(string $slug): ?array
    {
        foreach ($this->items as $item) {
            if ($item['slug'] === $slug) {
                return $item;
            }
        }

        return null;
    }

    public function facts(array $item): array
    {
        return (array) ($item['facts'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $criteria
     */
    private function matches(array $item, array $criteria): bool
    {
        if (isset($criteria['area']) && mb_stripos((string) $item['area'], (string) $criteria['area']) === false) {
            return false;
        }

        if (isset($criteria['beds']) && (int) $item['beds'] !== (int) $criteria['beds']) {
            return false;
        }

        if (isset($criteria['deal']) && $item['deal'] !== $criteria['deal']) {
            return false;
        }

        if (isset($criteria['type']) && mb_strtolower((string) $item['type']) !== mb_strtolower((string) $criteria['type'])) {
            return false;
        }

        if (isset($criteria['price_min']) && (int) $item['price'] < (int) $criteria['price_min']) {
            return false;
        }

        if (isset($criteria['price_max']) && (int) $item['price'] > (int) $criteria['price_max']) {
            return false;
        }

        return true;
    }
}
