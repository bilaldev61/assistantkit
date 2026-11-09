<?php

namespace AssistantKit\Rules;

use AssistantKit\Pack\RulesPack;
use AssistantKit\Support\Text;
use AssistantKit\Value\Conversation;
use AssistantKit\Value\Reply;

/**
 * The free layer. Regex against the visitor's message, sentences straight from
 * config: no query, no tokens, and it still works with the API key deleted.
 */
final class QuickAnswers
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly RulesPack $pack,
        private readonly array $config,
    ) {}

    /**
     * @param  array<string, string>  $facts  sentences about the item in view, from Catalogue::facts()
     */
    public function answer(Conversation $conversation, string $message, array $facts = []): ?Reply
    {
        $text = Text::normalise($message);

        if ($text === '') {
            return null;
        }

        if ($reply = $this->agency($text)) {
            return $reply;
        }

        if ($reply = $this->fact($conversation, $text, $facts)) {
            return $reply;
        }

        if (count(Text::words($text)) <= 8 && $this->pack->matches('thanks', $text)) {
            return new Reply($this->thanks($conversation), 'rule:thanks');
        }

        if (count(Text::words($text)) <= 6 && $this->pack->matches('greeting', $text)) {
            return new Reply($this->greeting(), 'rule:greeting');
        }

        return null;
    }

    private function agency(string $text): ?Reply
    {
        $brand = (array) ($this->config['brand'] ?? []);

        foreach ($this->pack->agency() as $intent => $pattern) {
            if (preg_match($pattern, $text) !== 1) {
                continue;
            }

            $reply = match ($intent) {
                'contact' => $this->contactLine($brand),
                'email'   => isset($brand['email']) ? 'You can email us at ' . $brand['email'] . '.' : null,
                'hours'   => isset($brand['hours']) ? 'We are open ' . $brand['hours'] . '.' : null,
                'office'  => isset($brand['office']) ? 'We are at ' . $brand['office'] . '.' : null,
                'areas'   => $this->areasLine($brand),
                default   => null,
            };

            if ($reply !== null) {
                return new Reply($reply, 'rule:agency_' . $intent);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $brand */
    private function contactLine(array $brand): ?string
    {
        $phone = $brand['phone'] ?? null;
        $whatsapp = $brand['whatsapp'] ?? null;

        if (! is_string($phone) || $phone === '') {
            return null;
        }

        $line = 'You can call us on ' . $phone;

        return is_string($whatsapp) && $whatsapp !== ''
            ? $line . ', or message us on WhatsApp at https://wa.me/' . $whatsapp . '.'
            : $line . '.';
    }

    /** @param array<string, mixed> $brand */
    private function areasLine(array $brand): ?string
    {
        $areas = (array) ($brand['areas'] ?? []);

        if ($areas === []) {
            return null;
        }

        $last = array_pop($areas);

        return 'We work across ' . (count($areas) > 0 ? implode(', ', $areas) . ' and ' . $last : $last) . '.';
    }

    /**
     * A recorded fact from the item answers first. Without one, the question is
     * deferred to the team - unless it reads like a search, which belongs to the
     * listing layer.
     *
     * @param  array<string, string>  $facts
     */
    private function fact(Conversation $conversation, string $text, array $facts): ?Reply
    {
        foreach ($this->pack->facts() as $key => $rule) {
            $pattern = $rule['match'] ?? null;

            if (! is_string($pattern) || preg_match($pattern, $text) !== 1) {
                continue;
            }

            $factKey = $rule['fact'] ?? null;

            if (is_string($factKey) && isset($facts[$factKey]) && $facts[$factKey] !== '') {
                return new Reply($facts[$factKey], 'rule:fact_' . $key);
            }

            $subject = $rule['defer'] ?? null;

            if (is_string($subject) && $subject !== '' && $this->deferrable($conversation, $text)) {
                return $this->defer($conversation, $subject, $key);
            }
        }

        return null;
    }

    private function deferrable(Conversation $conversation, string $text): bool
    {
        if ($conversation->itemSlug !== null) {
            return true;
        }

        // "3 bed under 2m in marina" is a search, not a question about one item.
        if (Text::hasDigit($text) || $this->pack->matches('search', $text)) {
            return false;
        }

        foreach ($this->pack->areas() as $area) {
            if (str_contains($text, Text::normalise($area))) {
                return false;
            }
        }

        return true;
    }

    private function defer(Conversation $conversation, string $subject, string $topic): Reply
    {
        $text = 'I do not want to guess about ' . $subject . ', so I will not. ';

        $text .= $conversation->isLead()
            ? 'I have your details and ' . $this->from('agent') . ' will confirm it.'
            : $this->ask('details');

        return new Reply($text, 'rule:deferral_' . $topic);
    }

    private function greeting(): string
    {
        return 'Hello. Ask me about ' . $this->pack->noun('many')
            . ', what is available, or how the process works - or tell me what you are looking for.';
    }

    private function thanks(Conversation $conversation): string
    {
        if ($conversation->isLead()) {
            return 'Any time. ' . $this->from('agent') . ' has your details and will be in touch.';
        }

        return 'Any time. If you want ' . $this->from('agent') . ' to follow up, ' . mb_strtolower($this->ask('details'));
    }

    private function ask(string $key): string
    {
        $asks = (array) ($this->config['asks'] ?? []);
        $template = (string) ($asks[$key] ?? 'Leave your name and email here and :agent will come back to you.');

        return Text::fill($template, ['agent' => $this->from('agent'), 'brand' => $this->from('name')]);
    }

    private function from(string $key): string
    {
        $brand = (array) ($this->config['brand'] ?? []);

        return (string) ($brand[$key] ?? ($key === 'agent' ? 'the team' : ''));
    }
}
