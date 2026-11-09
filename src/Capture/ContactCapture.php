<?php

namespace AssistantKit\Capture;

use AssistantKit\Contracts\ConversationStore;
use AssistantKit\Contracts\LeadSink;
use AssistantKit\Support\Text;
use AssistantKit\Value\Conversation;
use AssistantKit\Value\Reply;

/**
 * The one behaviour that must never depend on the model: if the visitor types
 * an email, the chat becomes a lead before anybody answers anything.
 */
final class ContactCapture
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly LeadSink $leads,
        private readonly ConversationStore $store,
        private readonly array $config,
    ) {}

    public function capture(Conversation $conversation, string $message): ?Reply
    {
        if ($conversation->isLead()) {
            return null;
        }

        if (preg_match('/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/', $message, $match) !== 1) {
            return null;
        }

        $email = mb_strtolower($match[0]);
        $name = $this->name($message, $email);

        $leadRef = $this->leads->capture([
            'name'    => $name,
            'email'   => $email,
            'phone'   => $this->phone($message),
            'message' => $this->question($conversation),
            'item'    => $conversation->itemSlug,
            'source'  => 'chat',
            'chat'    => $conversation->id,
        ]);

        $conversation->leadRef = $leadRef;

        return new Reply($this->thanks($name, $email), 'lead');
    }

    /** "My name is Sara Ali" / "Ahmed Khan, ..." / otherwise the email local part. */
    private function name(string $message, string $email): ?string
    {
        $patterns = [
            '/\b(?:my name is|i am|i\'m|this is|name[:\s]+)\s*([\p{L}][\p{L}\'-]*(?:\s+[\p{L}][\p{L}\'-]*){0,2})/iu',
            '/^\s*([\p{L}][\p{L}\'-]*(?:\s+[\p{L}][\p{L}\'-]*){0,2})\s*(?:,|here)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, trim($message), $match) === 1) {
                return $this->title($match[1]);
            }
        }

        $local = strstr($email, '@', true);
        $local = is_string($local) ? preg_replace('/[^a-z]+/i', ' ', $local) : null;
        $local = is_string($local) ? trim($local) : '';

        return $local !== '' ? $this->title($local) : null;
    }

    /** A person's name is written in title case, not as a sentence. */
    private function title(string $value): string
    {
        return trim(mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8'));
    }

    private function phone(string $message): ?string
    {
        if (preg_match('/(?:\+\d[\d\s().-]{6,}\d|\b0\d[\d\s().-]{6,}\d)/', $message, $match) === 1) {
            return trim($match[0]);
        }

        return null;
    }

    /** The last thing the visitor asked before handing over the email. */
    private function question(Conversation $conversation): ?string
    {
        foreach (array_reverse($conversation->messages) as $message) {
            $content = trim((string) $message['content']);

            if ($message['role'] === 'user' && $content !== '' && ! str_contains($content, '@')) {
                return $content;
            }
        }

        return null;
    }

    private function thanks(?string $name, string $email): string
    {
        $brand = (array) ($this->config['brand'] ?? []);
        $agent = (string) ($brand['agent'] ?? 'the team');

        $opening = $name !== null && $name !== '' ? 'Thank you, ' . $name . ' - ' : 'Thank you - ';

        return $opening . 'I have passed this to ' . $agent . ', who will reply to ' . $email . '.';
    }
}
