<?php

/**
 * A rules pack is the vocabulary of one industry. The engine is the same for
 * every pack; only the words change. Copy this file to start a new industry.
 */
return [
    'name'  => 'property',

    /*
     | The tools this trade declares. An allowlist, not a list of exceptions:
     | an order-status tool added to the engine later is not inherited here,
     | because this pack never asks for it.
     */
    'tools' => [
        'search_items',
        'get_item',
        'save_lead',
        'record_unanswered_question',
    ],

    'nouns' => [
        'one'   => 'property',
        'many'  => 'properties',
        'thing' => 'listing',
    ],

    'types' => [
        'apartment' => 'Apartment',
        'flat'      => 'Apartment',
        'villa'     => 'Villa',
        'townhouse' => 'Townhouse',
        'penthouse' => 'Penthouse',
        'studio'    => 'Studio',
        'office'    => 'Office',
    ],

    /*
     | Sent to Catalogue::search() as criteria. The adapter decides how to
     | apply them; the engine only recognises the words.
     */
    'criteria' => [
        'beds'  => '/\b(?:(\d+)\s*(?:bed|beds|bedroom|bedrooms|bhk|br)\b|(studio)\b)/i',
        'price' => [
            'min' => '/\b(?:from|over|above|minimum|at least|more than)\s*(?:aed\s*)?([\d.,]+)\s*(k|m|million|thousand)?/i',
            'max' => '/\b(?:under|below|up to|max|maximum|less than|budget of|budget is)\s*(?:aed\s*)?([\d.,]+)\s*(k|m|million|thousand)?/i',
        ],
        'rent'  => '/\b(rent|rental|renting|per year|p\.a\.?|annual|monthly|per month)\b/i',
        'sale'  => '/\b(buy|buying|purchase|for sale|sales|own it)\b/i',
        'type'  => '/\b(apartment|flat|villa|townhouse|penthouse|studio|office)\b/i',
        'area'  => '/\b(in|at|around|near|close to)\s+([a-z][a-z\s]{2,30})/i',
    ],

    // Written the way people type it on the left, matched to the area name.
    'aliases' => [
        'jvc'      => 'Jumeirah Village Circle',
        'marina'   => 'Dubai Marina',
        'downtown' => 'Downtown Dubai',
        'difc'     => 'DIFC',
        'business bay' => 'Business Bay',
    ],

    'areas' => [
        'Dubai Marina',
        'Jumeirah Village Circle',
        'Downtown Dubai',
        'DIFC',
        'Business Bay',
    ],

    /*
     | Recognised questions. `fact` is a key the catalogue adapter can supply a
     | ready sentence for; `defer` is what to say when it does not.
     */
    'facts' => [
        // Negotiation and fees come first: "is the price negotiable?" mentions the
        // price but must never be answered with the advertised figure.
        'negotiable' => [
            'match' => '/\b(negotiable|discount|reduce the price|lower the price|best price|deal)\b/i',
            'defer' => 'whether there is room on the price',
        ],
        'fees' => [
            'match' => '/\b(fees?|commission|service charge|deposit|tax|dld)\b/i',
            'defer' => 'the exact fees for this one',
        ],
        'price' => [
            'match' => '/\b(price|cost|how much|asking|rent|rate|aed)\b/i',
            'fact'  => 'price',
            'defer' => 'whether there is room on the price',
        ],
        'bedrooms' => [
            'match' => '/\b(how many (beds|bedrooms)|bedrooms?|layout)\b/i',
            'fact'  => 'bedrooms',
            'defer' => 'the exact layout',
        ],
        'size' => [
            'match' => '/\b(size|sqft|sq\.? ?ft|square (feet|foot|metres|meters)|built[- ]up)\b/i',
            'fact'  => 'size',
            'defer' => 'the built-up size',
        ],
        'availability' => [
            'match' => '/\b(available|availability|still (there|on the market)|vacant|move[- ]?in)\b/i',
            'fact'  => 'availability',
            'defer' => 'when it is free to move in',
        ],
        'furnishing' => [
            'match' => '/\b(furnished|unfurnished|furniture)\b/i',
            'fact'  => 'furnishing',
            'defer' => 'whether it comes furnished',
        ],
        'handover' => [
            'match' => '/\b(handover|completion|off[- ]plan|ready now)\b/i',
            'fact'  => 'handover',
            'defer' => 'the handover date',
        ],
        'parking' => [
            'match' => '/\b(parking|garage|car park)\b/i',
            'fact'  => 'parking',
            'defer' => 'the parking allocation',
        ],
    ],

    'process' => [
        'renting' => '/\b(rent|renting|tenant|tenancy)\b/i',
        'buying'  => '/\b(buy|buying|purchase|buyer)\b/i',
    ],

    'agency' => [
        'contact' => '/\b(phone|call|contact|whatsapp|number|reach you|speak to)\b/i',
        'email'   => '/\b(e-?mail)\b/i',
        'hours'   => '/\b(hours|open|opening|timings?|closed|weekend)\b/i',
        'office'  => '/\b(office|address|located|location|based)\b/i',
        'areas'   => '/\b(which areas|what areas|areas do you|cover)\b/i',
    ],

    'intents' => [
        'greeting' => '/^(hi|hey|hello|good (morning|afternoon|evening)|salaam|salam)\b/i',
        'thanks'   => '/\b(thanks|thank you|appreciate it|that helps|perfect|got it)\b/i',
        'search'   => '/\b(looking for|do you have|show me|any |search|options|available|list)\b/i',
        'judgement' => '/\b(compare|better|best|recommend|should i|why|explain|difference|worth it|invest)\b/i',
    ],
];
