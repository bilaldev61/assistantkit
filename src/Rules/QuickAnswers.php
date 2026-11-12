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

        // Before agency: "what is your RERA number" names a number, and the
        // contact rule would answer it with a phone number.
        if ($this->pack->matches('trust', $text)) {
            return $this->trust($conversation);
        }

        if ($reply = $this->agency($text)) {
            return $reply;
        }

        if ($this->pack->matches('identity', $text)) {
            return $this->identity();
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

    /**
     * A hello or a thanks on its own - small talk rather than a question.
     *
     * The repeat guard uses it: a visitor saying hello twice is not stuck on an
     * answer, so their second hello still gets a hello back.
     */
    public function isSmallTalk(string $message): bool
    {
        $text = Text::normalise($message);
        $words = count(Text::words($text));

        return ($words <= 8 && $this->pack->matches('thanks', $text))
            || ($words <= 6 && $this->pack->matches('greeting', $text));
    }

    /**
     * The last gate before a model call: nothing above could answer this, so is
     * it even about this trade? Asked here rather than up front so that contact,
     * hours and area questions - which carry no trade word - still answer free.
     */
    public function isOffTopic(Conversation $conversation, string $message): bool
    {
        $scope = (array) ($this->config['scope'] ?? []);

        if (! ($scope['gate'] ?? true)) {
            return false;
        }

        // With an item open every question is about that item until the visitor
        // says otherwise, so there is nothing here to gate.
        if ($conversation->itemSlug !== null) {
            return false;
        }

        $text = Text::normalise($message);

        // A handful of words carries no topic of its own - "tell me more", "is
        // it available". Those are continuations, and the model has the history.
        if ($text === '' || count(Text::words($text)) <= 4) {
            return false;
        }

        // A digit is a budget, a phone number or a bedroom count - all of them
        // this trade's business. The search intent is deliberately not a signal:
        // it matches a bare "any", and "any threats from iran" is not a search.
        if ($this->pack->matches('in_scope', $text)
            || $this->pack->matches('distress', $text)
            || $this->pack->matches('judgement', $text)
            || Text::hasDigit($text)) {
            return false;
        }

        // Distress anywhere in the thread stands the gate down: a later message
        // in an emergency conversation is not a new off-topic visitor.
        foreach ($conversation->messages as $said) {
            if ($this->pack->matches('distress', Text::normalise((string) $said['content']))) {
                return false;
            }
        }

        return true;
    }

    public function offTopic(): Reply
    {
        return new Reply(
            'I can only help with ' . $this->pack->noun('many')
            . ' - what we have, prices, and arranging a call with ' . $this->from('agent')
            . '. What are you looking for?',
            'rule:off_topic',
        );
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

    private function identity(): Reply
    {
        $agent = $this->from('agent');

        return new Reply(
            'I\'m the website assistant for ' . $this->from('name') . ' - an AI tool that answers from our records. '
            . 'For anything that needs a human, ' . $agent . ' and the team handle the real side.',
            'rule:identity',
        );
    }

    /**
     * "Are you a scam?" / "are you registered?" - asked by someone deciding
     * whether to trust us with money, so this is the one place a guess does the
     * most damage. The registration is quoted only if the site put a real one in
     * config; otherwise it is the team's to confirm.
     */
    private function trust(Conversation $conversation): Reply
    {
        $licence = trim($this->from('licence'));

        $text = $licence !== ''
            ? $this->from('name') . ' is registered under ' . $licence . '.'
            : 'I cannot verify that from this chat and I will not guess. ' . $this->from('agent')
                . ' can send you the registration and licence details directly.';

        $text .= $conversation->isLead()
            ? ' I have your details and ' . $this->from('agent') . ' will be in touch.'
            : ' ' . $this->ask('details');

        return new Reply($text, 'rule:agency_trust');
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
