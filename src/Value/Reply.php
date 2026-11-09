<?php

namespace AssistantKit\Value;

/**
 * One answer, plus how it was produced. `kind` is what makes the transcript
 * reportable: "how many answers came from rules, and how many needed the model".
 *
 * Known kinds: lead, limit, fallback, ai, rule:<intent>, rule:fact_<key>,
 * rule:deferral, rule:process, rule:search, rule:search_none.
 */
final class Reply
{
    public function __construct(
        public readonly string $text,
        public readonly string $kind,
    ) {}

    public function isRule(): bool
    {
        return str_starts_with($this->kind, 'rule:');
    }
}
