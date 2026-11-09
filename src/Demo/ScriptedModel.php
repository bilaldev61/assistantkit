<?php

namespace AssistantKit\Demo;

use AssistantKit\Contracts\LanguageModel;

/**
 * Stands in for a provider. Either answers with a fixed line, or plays the
 * "I cannot answer this" turn: files the question, then replies - which is the
 * path the engine must still decorate with an email ask.
 */
final class ScriptedModel implements LanguageModel
{
    /** @var list<string> */
    public array $prompts = [];

    public function __construct(
        private readonly ?string $answer = null,
        private readonly bool $available = true,
    ) {}

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function converse(string $system, array $messages, array $tools, callable $runTool): string
    {
        $this->prompts[] = $system;

        if ($this->answer === null) {
            return 'I am not sure about that.';
        }

        if ($this->answer === 'defer') {
            $question = '';

            foreach (array_reverse($messages) as $message) {
                if ($message['role'] === 'user') {
                    $question = $message['content'];
                    break;
                }
            }

            $runTool('record_unanswered_question', ['question' => $question]);

            return 'I cannot answer that one from what I have, sorry.';
        }

        return $this->answer;
    }
}
