<?php

namespace AssistantKit\Contracts;

use AssistantKit\Value\Conversation;

/**
 * Where a chat lives between requests. In Laravel this is three tables; in a
 * prototype it is an array. The engine only ever asks for these five things.
 */
interface ConversationStore
{
    public function load(string $id, ?string $itemSlug = null): Conversation;

    public function save(Conversation $conversation): void;

    /** A question neither the rules nor the model could answer. */
    public function recordQuestion(Conversation $conversation, string $question, ?string $itemSlug = null): void;

    /**
     * Already filed questions for this chat, so the report and the per-chat cap
     * both work without a second query shape.
     *
     * @return list<array{question: string, item: string|null}>
     */
    public function questions(Conversation $conversation): array;
}
