<?php

namespace AssistantKit\Contracts;

/**
 * Optional. With no implementation the assistant still answers everything the
 * rules cover, and says so plainly for the rest.
 */
interface LanguageModel
{
    /** False when there is no key, or the admin switched the model off. */
    public function isAvailable(): bool;

    /**
     * Hand the conversation over and let the model call the tools it wants.
     *
     * @param  list<array{role: string, content: string}>  $messages  oldest first, visitor and assistant turns
     * @param  list<array<string, mixed>>  $tools  JSON-schema definitions, already filtered for read-only use
     * @param  callable(string, array<string, mixed>): mixed  $runTool  runs one tool call and returns its result
     * @return string the model's answer
     */
    public function converse(string $system, array $messages, array $tools, callable $runTool): string;
}
