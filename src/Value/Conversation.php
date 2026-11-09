<?php

namespace AssistantKit\Value;

final class Conversation
{
    /** @var list<array{role: string, content: string, answered_by: string|null}> */
    public array $messages = [];

    /** The host's reference for the lead this chat turned into, once it has. */
    public ?string $leadRef = null;

    public function __construct(
        public readonly string $id,
        public ?string $itemSlug = null,
        public int $visitorMessages = 0,
    ) {}

    public function recordVisitor(string $content): void
    {
        $this->messages[] = ['role' => 'user', 'content' => $content, 'answered_by' => null];
        $this->visitorMessages++;
    }

    public function recordAssistant(string $content, string $answeredBy): void
    {
        $this->messages[] = ['role' => 'assistant', 'content' => $content, 'answered_by' => $answeredBy];
    }

    public function isLead(): bool
    {
        return $this->leadRef !== null;
    }

    /**
     * What the model is allowed to see: the last N turns, and only words, so
     * an internal label never leaks into the prompt.
     *
     * @return list<array{role: string, content: string}>
     */
    public function turns(int $limit = 0): array
    {
        $turns = array_map(
            fn(array $message): array => ['role' => $message['role'], 'content' => $message['content']],
            $this->messages,
        );

        return $limit > 0 ? array_slice($turns, -$limit) : $turns;
    }
}
