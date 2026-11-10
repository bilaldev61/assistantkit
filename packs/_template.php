<?php

/**
 * A blank pack. Copy to packs/<your-niche>.php and fill in every value.
 *
 * The engine reads these keys; the words are yours. Order matters inside
 * `facts`: the first matching entry wins, so put the narrow intents first.
 */
return [
    'name'  => 'CHANGE_ME',

    // How you refer to one and many. Used in every generated sentence.
    'nouns' => [
        'one'   => 'item',
        'many'  => 'items',
        'thing' => 'record',
    ],

    // Words the visitor types => the label you want to read back.
    'types' => [
        'widget' => 'Widget',
        'gadget' => 'Gadget',
    ],

    /*
     | Regexes that turn a message into Catalogue::search() criteria.
     | The engine only recognises words; your adapter applies them.
     */
    'criteria' => [
        'beds'  => '/\b(\d+)\s*(?:bed|bedrooms?)\b/i',
        'price' => [
            'min' => '/\b(?:from|over|above|at least)\s*([\d.,]+)\s*(k|m|million|thousand)?/i',
            'max' => '/\b(?:under|below|up to|max)\s*([\d.,]+)\s*(k|m|million|thousand)?/i',
        ],
        'rent'  => '/\b(rent|renting|per month|monthly)\b/i',
        'sale'  => '/\b(buy|buying|purchase|for sale)\b/i',
        // Write the plural inside the pattern: a type word that only matches one
        // number drops the filter and shows the visitor the whole shelf.
        'type'  => '/\b(widget|gadget)s?\b/i',
        'area'  => '/\b(in|at|around|near)\s+([a-z][a-z\s]{2,30})/i',
    ],

    // How people type a place => the name your data uses.
    'aliases' => [
        'town centre' => 'Town Centre',
    ],

    // Only these are treated as real places. Anything else is ignored.
    'areas' => [
        'Town Centre',
        'Riverside',
    ],

    /*
     | Question -> answer. `fact` is a key your Catalogue::facts() can supply;
     | `defer` is the sentence used when it cannot, and must ask for a way back.
     | Narrow patterns first: "is the price negotiable" must not hit `price`.
     */
    'facts' => [
        'negotiable' => [
            'match' => '/\b(negotiable|discount|best price)\b/i',
            'defer' => 'whether there is room on the price',
        ],
        'price' => [
            'match' => '/\b(price|cost|how much)\b/i',
            'fact'  => 'price',
            'defer' => 'the current price',
        ],
        'availability' => [
            'match' => '/\b(available|in stock|lead time)\b/i',
            'fact'  => 'availability',
            'defer' => 'when it will be available',
        ],
    ],

    // Procedural topics answered from config. No digits allowed in these steps.
    'process' => [
        'buying'   => '/\b(buy|buying|order|ordering)\b/i',
        'returns'  => '/\b(return|refund|warranty)\b/i',
        'viewings' => '/\b(viewings?|inspection|appointment)\b/i',
    ],

    // Questions about you rather than the items.
    'agency' => [
        'contact' => '/\b(phone|call|contact|whatsapp|reach you)\b/i',
        'email'   => '/\b(e-?mail)\b/i',
        // Tolerate the typo, refuse the measurement: "hourss" is still a question
        // about opening times, "24 hours" is not.
        'hours'   => '/\b(timings?|(?<!\d )h[ou]*rs*|opening (?:hours?|time)|do you open|are you open)\b/i',
        'office'  => '/\b(office|address|located|where are you)\b/i',
        'areas'   => '/\b(which areas|what areas|cover)\b/i',
    ],

    'intents' => [
        'greeting'  => '/^(?:hi+|h[ae]l+o+|hl+o*w?|hey+|hy+|hai|sala+m|slm|assalam[u]? ?o? ?alaikum|aoa|yo|good (?:morning|afternoon|evening|day))(?:\s+(?:ge|ji|yaar|sahab|sir|bhai|na|haan|bas|to))*\s*$/i',
        'thanks'    => '/\b(thanks|thank you|appreciate it)\b/i',
        'identity'  => '/\b(?:are you|you(?:\'re| are)?)\s+(?:an?\s+|the\s+)?(?:real|human|person|ai|bot|robot|artificial|chat.?gpt|openai)\b|\b(?:who\s+(?:made|built|created)\s+you|what\s+are\s+you|is\s+this\s+(?:ai|a\s+bot|a\s+robot))\b/i',
        'search'    => '/\b(looking for|do you have|show me|any |options|list)\b/i',
        'judgement' => '/\b(compare|better|best|recommend|should i|why|worth it)\b/i',
    ],

    /*
     | The tool menu for this niche — an allowlist, not a list of exceptions.
     | A name is only offerable when it appears here *and* a handler is
     | registered for it; built-ins cannot be replaced. Drop a name to switch
     | that action off for this site, and add your own (see examples/tools/
     | and TOOLS.md) only when your data can actually answer it.
     */
    'tools' => [
        'search_items',
        'get_item',
        'save_lead',
        'record_unanswered_question',
    ],
];
