<?php

/**
 * Expected behaviour of the property pack, one line per question.
 *
 *   php bin/rules.php cases
 *
 * Every row runs through the real engine with the demo catalogue, no model. When
 * you change a rule, one of these fails and names the question. Add a row for
 * every bug you fix.
 *
 * Fields: ask, item (optional slug), kind or kinds, contains, missing.
 */
return [
    'pack'  => 'property',
    'cases' => [
        [
            'ask'      => 'how much is it?',
            'item'     => 'marina-2br',
            'kind'     => 'rule:fact_price',
            'contains' => 'AED 2,100,000',
        ],
        [
            'ask'   => 'is the price negotiable?',
            'item'  => 'marina-2br',
            'kind'  => 'rule:deferral_negotiable',
            // The whole point of the ordering rule: it must not quote the figure.
            'missing' => 'AED 2,100,000',
        ],
        [
            'ask'   => 'are there any fees?',
            'item'  => 'marina-2br',
            'kind'  => 'rule:deferral_fees',
        ],
        [
            'ask'  => 'how many bedrooms?',
            'item' => 'marina-2br',
            'kind' => 'rule:fact_bedrooms',
        ],
        [
            'ask'  => 'what size is it?',
            'item' => 'marina-2br',
            'kind' => 'rule:fact_size',
        ],
        [
            'ask'  => 'is it still available?',
            'item' => 'marina-2br',
            'kind' => 'rule:fact_availability',
        ],
        [
            'ask'  => 'is it furnished?',
            'item' => 'marina-2br',
            'kind' => 'rule:fact_furnishing',
        ],
        [
            'ask'  => 'is there parking?',
            'item' => 'marina-2br',
            'kind' => 'rule:fact_parking',
        ],
        [
            'ask'  => 'when is the handover?',
            'item' => 'marina-2br',
            'kind' => 'rule:fact_handover',
        ],
        [
            'ask'  => 'how does renting work',
            'kind' => 'rule:process',
        ],
        [
            'ask'  => 'what are your hours?',
            'kind' => 'rule:agency_hours',
        ],
        [
            'ask'  => 'where are you located?',
            'kind' => 'rule:agency_office',
        ],
        [
            'ask'  => 'what is your phone number',
            'kind' => 'rule:agency_contact',
        ],
        [
            'ask'  => 'which areas do you cover?',
            'kind' => 'rule:agency_areas',
        ],
        [
            'ask'  => 'do you have a 2 bedroom apartment in dubai marina for sale',
            'kind' => 'rule:search',
        ],
        [
            'ask'  => 'hi, do you have any studio in jvc for rent',
            'kind' => 'rule:search',
        ],
        [
            'ask'  => '2 bed under 2m in marina',
            'kind' => 'rule:search_none',
        ],
        [
            'ask'  => 'hello',
            'kind' => 'rule:greeting',
        ],
        [
            'ask'  => 'thanks',
            'kind' => 'rule:thanks',
        ],
        [
            'ask'      => 'please call me back, sara@example.com',
            'kind'     => 'lead',
            'contains' => 'Nadia',
        ],
        [
            // A question about nothing we know: it must be filed, not guessed.
            'ask'  => 'do you accept bitcoin?',
            'kind' => 'fallback',
        ],
        [
            // An unknown place is not a filter: "at night" must not search Night.
            'ask'      => 'is the building quiet at night?',
            'kind'     => 'fallback',
            'missing'  => 'Night',
        ],
        [
            // Typed, not proofread. The model could not answer this one anyway.
            'ask'      => 'office hourss',
            'kind'     => 'rule:agency_hours',
            'contains' => 'Saturday to Thursday',
        ],
        [
            // "24 hours" measures a gym, not our timings.
            'ask'     => 'is there a 24 hours gym in the building',
            'kind'    => 'fallback',
            'missing' => 'Saturday to Thursday',
        ],
        [
            // The plural is the word visitors use; losing the filter would put
            // the villa in front of someone who asked for apartments.
            'ask'     => 'apartments in dubai marina for sale',
            'kind'    => 'rule:search',
            'missing' => '4-bedroom villa',
        ],
    ],
];
