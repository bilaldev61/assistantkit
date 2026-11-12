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
        // The plural belongs in the pattern: "apartments in Dubai Marina" that
        // never matches does not filter to nothing, it filters to everything,
        // and the villas get listed at someone who asked for apartments.
        'type'  => '/\b(apartment|flat|villa|townhouse|penthouse|studio|office)s?\b/i',
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
        'viewing' => '/\b(viewings?|inspection)\b/i',
    ],

    'agency' => [
        'contact' => '/\b(phone|call|contact|whatsapp|number|reach you|speak to)\b/i',
        'email'   => '/\b(e-?mail)\b/i',
        // Typed, not proofread: "office hourss" and "our hrs" are the same
        // question, so the stem has to survive the typo. The lookbehind stops "a
        // 24 hours gym" from reading as opening times, and the phrases do the
        // work a bare "open" used to do - "open to offers" is not about timings.
        'hours'   => '/\b(timings?|(?<!\d )h[ou]*rs*|opening (?:hours?|time)|do you open|are you open|are you closed|what time do you (?:open|close)|kab khul|kitne baje)\b/i',
        'office'  => '/\b(office|address|located|location|based)\b/i',
        'areas'   => '/\b(which areas|what areas|areas do you|cover)\b/i',
    ],

    'intents' => [
        // Typo variants ("hlo", "slm") are what visitors actually type.
        // Trailing fillers ("ge", "ji") are sentence-final noise in South-Asian English.
        'greeting'  => '/^(?:hi+|h[ae]l+o+|hl+o*w?|hey+|hy+|hai|sala+m|slm|assalam[u]? ?o? ?alaikum|aoa|yo|good (?:morning|afternoon|evening|day))(?:\s+(?:ge|ji|yaar|sahab|sir|bhai|na|haan|bas|to))*\s*$/i',
        'thanks'    => '/\b(thanks|thank you|appreciate it|that helps|perfect|got it)\b/i',
        'identity'  => '/\b(?:are you|you(?:\'re| are)?)\s+(?:an?\s+|the\s+)?(?:real|human|person|ai|bot|robot|artificial|chat.?gpt|openai)\b|\b(?:who\s+(?:made|built|created)\s+you|what\s+are\s+you|is\s+this\s+(?:ai|a\s+bot|a\s+robot))\b/i',
        'search'    => '/\b(looking for|do you have|show me|any |search|options|available|list)\b/i',
        'judgement' => '/\b(compare|better|best|recommend|should i|why|explain|difference|worth it|invest)\b/i',
        // Is this business real and licensed? A registration is a legal claim
        // made in the company's name, so it is answered from brand.licence in
        // config and never inferred. Add your own brand name to the subjects.
        'trust'     => '/\b(scam|fraud\w*|looting|cheating|legit|legitimate|trustworthy|dodgy|shady)\b|\b(?:you|u|your|agency|company|business)\b[^.?!]{0,30}\b(licen[cs]e[ds]?|licen[cs]ing|registered|registration|rera|orn)\b|\b(?:rera|orn|trade)\s+(?:number|no|licen[cs]e|registration|certificate)\b/i',
        // What makes a message worth a model call. An allowlist, not a denylist:
        // a visitor invents an off-topic question faster than a list of banned
        // topics can name it. Place names are deliberately absent - "dubai visas"
        // is about visas, and one place word must not switch the gate off.
        'in_scope'  => '/\b(house|villa|home|ghar|plot|land|flat|apartment|townhouse|property|properties|penthouse|studio|marla|kanal|crore|lakh|aed|dirham|price|cost|budget|rent|rental|buy|buying|sell|sale|listing|listings|bed|bath|room|sq ?ft|square (?:feet|foot)|area|areas|community|suburb|neighbou?rhood|nearby|owner|seller|landlord|developer|tenant|agent|agency|broker|viewing|visit|inspection|offer|negotiat\w*|discount|mortgage|loan|instal+ments?|title|deed|noc|ejari|oqood|dld|rera|orn|permit|licen[cs]e\w*|registered|registration|service charge|maintenance|dewa|handover|possession|completion|off ?plan|furnish\w*|parking|garage|balcony|gym|pool|school|metro|mall|hospital|beach|park|airport|cheaper|similar|suggest)\b/i',
        // A person in distress is not an off-topic visitor, and no commercial
        // scope rule should talk over them. The gate stands down for the thread.
        'distress'  => '/\b(kidnap\w*|abduct\w*|hostage|emergency|ambulance|police|999|911|in danger|help me|suicid\w*|kill (?:myself|me)|heart attack|trapped|threaten\w*|attacked|bleeding|dying)\b/i',
    ],
];
