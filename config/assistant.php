<?php

/**
 * Everything a host site says out loud lives here. The engine reads these
 * arrays and never hardcodes a phone number, an email or a process step.
 */
return [
    'brand' => [
        'name'     => 'Example Properties',
        'agent'    => 'Nadia',
        'email'    => 'hello@example.com',
        'phone'    => '+971 00 000 0000',
        'whatsapp' => null,
        'hours'    => 'Saturday to Thursday, 9am to 6pm',
        'office'   => 'Office 101, Example Tower, Dubai',
        'areas'    => ['Dubai Marina', 'Jumeirah Village Circle', 'Downtown Dubai'],
    ],

    /*
     | Procedural answers ("how does renting work"). Rendered verbatim, so a
     | figure that needs context stays out and is deferred to the team.
     */
    'process' => [
        'renting' => [
            'title' => 'How renting works with us',
            'steps' => [
                'You tell us the area, budget and move-in date.',
                'We shortlist what is actually available and send it to you.',
                'You view the ones you like and we arrange the viewings.',
                'You agree terms, sign the tenancy contract and collect the keys.',
            ],
            'note' => 'The deposit and the fees are confirmed in writing before you sign.',
        ],
        'buying' => [
            'title' => 'How buying works with us',
            'steps' => [
                'You tell us the area, budget and whether it is for living in or letting out.',
                'We shortlist what is available and send it to you.',
                'You view the ones you like and we arrange the viewings.',
                'You agree terms, sign the sale agreement and we handle the transfer.',
            ],
            'note' => 'The fees and the transfer dates are confirmed in writing before you commit.',
        ],
        'viewing' => [
            'title' => 'How viewings work',
            'steps' => [
                'Find a property you like on the listings page or through this chat.',
                'Use the enquiry form or tell us which one and when suits.',
                ':agent confirms a time and meets you there - same or next day in most cases.',
                'Bring questions about price, payment or possession to the viewing.',
            ],
            'note' => ':agent will arrange the time directly with you - there is no online booking system.',
        ],
    ],

    'limits' => [
        'history'            => 12,  // turns handed to the model
        'max_messages'       => 30,  // visitor messages before a hand-off
        'questions_per_chat' => 5,   // questions filed as unanswered per chat
        'results'            => 6,   // rows listed in a search answer
    ],

    // :agent and :brand are replaced from brand above.
    'asks' => [
        'details' => 'Leave your name and email here and :agent will come back to you.',
        'handoff' => 'Send me your name and email and :agent will take it from there.',
    ],

    /*
     | Which tools this site offers to the model. null means "use the pack's
     | allowlist as written"; an array can only narrow that list, never widen
     | it, and [] switches the tool menu off entirely. Fail-closed by design:
     | a name with no registered handler is dropped, never advertised.
     | See TOOLS.md.
     */
    'tools' => null,

    /*
     | Tools that read someone's own record (an order, an invoice, a booking)
     | declare requiresAuth and stay hidden unless this is true. Report the
     | real session state of the request here.
     */
    'visitor' => [
        'signed_in' => false,
    ],

    'pack' => 'property',
];
