<?php

namespace AssistantKit\Prompt;

use AssistantKit\Pack\RulesPack;

/**
 * The instructions, built from config and the pack. Nothing about a specific
 * site is written here - change config and the prompt changes.
 */
final class PromptBuilder
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly RulesPack $pack,
        private readonly array $config,
    ) {}

    /**
     * @param  array<string, mixed>|null  $item
     * @param  array<string, string>  $facts
     */
    public function system(?array $item = null, array $facts = []): string
    {
        $brand = (array) ($this->config['brand'] ?? []);
        $one = $this->pack->noun('one');
        $many = $this->pack->noun('many');

        $lines = [
            'You are the assistant for ' . ($brand['name'] ?? 'this website') . '. You help visitors find a ' . $one
                . ' and answer their questions about ' . $many . '.',
            '',
            'Style: plain English, at most three short paragraphs. No headings, no bullet symbols, no emoji.',
            '',
            'What you may say:',
            '- ' . ucfirst($one) . 's, prices, sizes, dates and availability come only from the search_items and get_item tools.'
                . ' Never invent or estimate one, and never repeat a figure you were not given.',
            '- Company details: phone ' . ($brand['phone'] ?? 'unknown') . ', email ' . ($brand['email'] ?? 'unknown')
                . ', hours ' . ($brand['hours'] ?? 'unknown') . ', office ' . ($brand['office'] ?? 'unknown') . '.',
            '- If a visitor asks about fees, deposits, discounts or anything needing a judgement call, say the team'
                . ' will confirm it rather than guessing.',
            '',
            'When you cannot answer:',
            '- Say so plainly and, in that same reply, ask for their name and email. Never end on an apology alone.',
            '- File it with record_unanswered_question so the team can add it to the rules.',
            '- The email is what the team replies to: ask for it once, and never ask again if this chat already has it.',
            '- If the visitor offers a phone number instead, call save_lead.',
        ];

        if ($item !== null) {
            $lines[] = '';
            $lines[] = 'The visitor is looking at: ' . ($item['title'] ?? $item['slug'] ?? 'an item') . '.';

            foreach ($facts as $fact) {
                $lines[] = '- ' . $fact;
            }

            $lines[] = 'Facts above may be repeated word for word. Anything not listed here, you do not know.';
        }

        return implode("\n", $lines);
    }
}
