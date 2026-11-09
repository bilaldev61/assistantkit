<?php

/**
 * A rules table for a bike shop — the input the builder eats.
 *
 *   php bin/rules-build.php rules/bikes.rules.php --out=packs/bikes.php
 *
 * Compare this to packs/bikes.php: the only thing you write is words. Every
 * `phrases` list becomes one escaped, ordered regex, and row order is priority —
 * so `negotiable` stays above `price`, which is what keeps "any discount?" from
 * quoting the asking price.
 *
 * `fact` is optional: with no fact, the question always defers to the team.
 * A raw regex is allowed in place of a phrase list when you need one.
 */
return [
    'name'  => 'bikes',

    'tools' => [
        'search_items',
        'get_item',
        'save_lead',
        'record_unanswered_question',
    ],

    'nouns' => [
        'one'   => 'bike',
        'many'  => 'bikes',
        'thing' => 'model',
    ],

    'types' => [
        'road bike'   => 'Road',
        'road'        => 'Road',
        'mtb'         => 'Mountain',
        'mountain bike' => 'Mountain',
        'hybrid'      => 'Hybrid',
        'gravel'      => 'Gravel',
    ],

    'criteria' => [
        'price' => [
            'min' => '/\b(?:from|over|above|at least)\s*(?:aed\s*)?([\d.,]+)\s*(k|thousand)?/i',
            'max' => '/\b(?:under|below|up to|max|budget of)\s*(?:aed\s*)?([\d.,]+)\s*(k|thousand)?/i',
        ],
        'rent'  => '/\b(rent|rental|hire)\b/i',
        'sale'  => '/\b(buy|buying|purchase|for sale)\b/i',
        'type'  => '/\b(road bike|road|mtb|mountain bike|hybrid|gravel)\b/i',
        'area'  => '/\b(in|at|near|around|close to)\s+([a-z][a-z\s]{2,30})/i',
    ],

    'aliases' => [
        'dxb' => 'Dubai',
        'auh' => 'Abu Dhabi',
    ],

    'areas' => [
        'Dubai',
        'Abu Dhabi',
        'Sharjah',
    ],

    /*
     | Row order is the answer order. Narrow intents above broad ones.
     */
    'facts' => [
        [
            'key'     => 'negotiable',
            'phrases' => ['negotiable', 'any discount', 'best price', 'deal on'],
            'defer'   => 'whether there is room on the price',
        ],
        [
            'key'     => 'finance',
            'phrases' => ['finance', 'instalments', 'installments', '0%', 'payment plan'],
            'defer'   => 'the finance options on this model',
        ],
        [
            'key'     => 'price',
            'phrases' => ['price', 'how much', 'cost'],
            'fact'    => 'price',
            'defer'   => 'the price of this model',
        ],
        [
            'key'     => 'stock',
            'phrases' => ['in stock', 'do you have', 'available', 'sizes left'],
            'fact'    => 'stock',
            'defer'   => 'current stock and the sizes left',
        ],
        [
            'key'     => 'sizing',
            'phrases' => ['what size', 'which size', 'size guide'],
            'fact'    => 'sizing',
            'defer'   => 'the sizes we carry for this model',
        ],
        [
            'key'     => 'delivery',
            'phrases' => ['delivery', 'deliver', 'shipping'],
            'fact'    => 'delivery',
            'defer'   => 'delivery to your area',
        ],
    ],

    'process' => [
        'sizing'    => ['how do you size', 'how do i know my size', 'fit'],
        'servicing' => ['servicing', 'service', 'tune up'],
    ],

    'agency' => [
        'contact' => ['phone', 'call', 'whatsapp'],
        'email'   => ['email', 'e-mail'],
        'hours'   => ['hours', 'open', 'opening'],
        'office'  => ['where are you', 'location', 'address', 'shop'],
    ],

    'intents' => [
        'greeting'  => ['hi', 'hello', 'hey'],
        'thanks'    => ['thanks', 'thank you', 'appreciate it'],
        'search'    => ['looking for', 'do you have', 'show me', 'options'],
        'judgement' => ['compare', 'better', 'best', 'recommend', 'should i'],
    ],
];
