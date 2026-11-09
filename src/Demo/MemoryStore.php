<?php

namespace AssistantKit\Demo;

use AssistantKit\Contracts\ConversationStore;
use AssistantKit\Value\Conversation;

/**
 * Chats kept in memory for one request. The Laravel binding replaces this with
 * three tables; nothing in the engine notices the difference.
 */
final class MemoryStore implements ConversationStore
{
    /** @var array<string, Conversation> */
    private array $conversations = [];

    /** @var array<string, list<array{question: string, item: string|null}>> */
    private array $questions = [];

    public function load(string $id, ?string $itemSlug = null): Conversation
    {
        if (! isset($this->conversations[$id])) {
            $this->conversations[$id] = new Conversation($id, $itemSlug);
        }

        return $this->conversations[$id];
    }

    public function save(Conversation $conversation): void
    {
        $this->conversations[$conversation->id] = $conversation;
    }

    public function recordQuestion(Conversation $conversation, string $question, ?string $itemSlug = null): void
    {
        $this->questions[$conversation->id][] = ['question' => $question, 'item' => $itemSlug];
    }

    public function questions(Conversation $conversation): array
    {
        return $this->questions[$conversation->id] ?? [];
    }
}
