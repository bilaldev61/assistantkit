<?php

/**
 * Demo-only chat storage: one JSON file per chat in the temp directory, so a
 * multi-turn conversation survives between requests of the PHP dev server.
 *
 * A real site uses its own tables. This exists so the widget can be seen
 * working with no database at all.
 */

use AssistantKit\Contracts\ConversationStore;
use AssistantKit\Contracts\LeadSink;
use AssistantKit\Value\Conversation;

function demo_chat_dir(): string
{
    $dir = sys_get_temp_dir() . '/assistantkit-demo';

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function demo_chat_path(string $chat): string
{
    return demo_chat_dir() . '/' . $chat . '.json';
}

/** @return array{messages: list<array<string, mixed>>, visitor: int, lead: string|null, questions: list<array<string, string|null>>} */
function demo_chat_read(string $chat): array
{
    $path = demo_chat_path($chat);

    if (! is_file($path)) {
        return ['messages' => [], 'visitor' => 0, 'lead' => null, 'questions' => []];
    }

    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) ? $data + ['messages' => [], 'visitor' => 0, 'lead' => null, 'questions' => []] : ['messages' => [], 'visitor' => 0, 'lead' => null, 'questions' => []];
}

function demo_chat_write(string $chat, array $data): void
{
    file_put_contents(demo_chat_path($chat), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

final class FileStore implements ConversationStore
{
    public function __construct(private readonly string $chat) {}

    public function load(string $id, ?string $itemSlug = null): Conversation
    {
        $data = demo_chat_read($this->chat);

        $conversation = new Conversation($id, $itemSlug, (int) $data['visitor']);
        $conversation->leadRef = is_string($data['lead'] ?? null) ? $data['lead'] : null;

        foreach ($data['messages'] as $message) {
            $conversation->messages[] = [
                'role'        => (string) ($message['role'] ?? 'user'),
                'content'     => (string) ($message['content'] ?? ''),
                'answered_by' => $message['answered_by'] ?? null,
            ];
        }

        return $conversation;
    }

    public function save(Conversation $conversation): void
    {
        $data = demo_chat_read($this->chat);
        $data['messages'] = $conversation->messages;
        $data['visitor'] = $conversation->visitorMessages;
        $data['lead'] = $conversation->leadRef;

        demo_chat_write($this->chat, $data);
    }

    public function recordQuestion(Conversation $conversation, string $question, ?string $itemSlug = null): void
    {
        $data = demo_chat_read($this->chat);
        $data['questions'][] = ['question' => $question, 'item' => $itemSlug];

        demo_chat_write($this->chat, $data);
    }

    public function questions(Conversation $conversation): array
    {
        return demo_chat_read($this->chat)['questions'];
    }
}

final class FileLeadSink implements LeadSink
{
    public function __construct(private readonly string $chat) {}

    public function capture(array $lead): string
    {
        $ref = 'demo-' . substr(bin2hex(random_bytes(4)), 0, 8);

        file_put_contents(
            demo_chat_dir() . '/leads.jsonl',
            json_encode($lead + ['ref' => $ref, 'chat' => $this->chat, 'at' => date('c')], JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND,
        );

        return $ref;
    }
}
