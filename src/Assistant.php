<?php

namespace AssistantKit;

use AssistantKit\Capture\ContactCapture;
use AssistantKit\Contracts\Catalogue;
use AssistantKit\Contracts\ConversationStore;
use AssistantKit\Contracts\LanguageModel;
use AssistantKit\Contracts\LeadSink;
use AssistantKit\Pack\RulesPack;
use AssistantKit\Prompt\PromptBuilder;
use AssistantKit\Rules\QuickAnswers;
use AssistantKit\Rules\RulesSearch;
use AssistantKit\Support\Text;
use AssistantKit\Tools\ToolBox;
use AssistantKit\Tools\ToolRegistry;
use AssistantKit\Value\Conversation;
use AssistantKit\Value\Reply;

/**
 * The whole engine, in the order it runs: capture, cap, rules, model, fallback.
 * Each layer is cheaper than the one after it, and the first layer that can
 * answer ends the turn.
 */
final class Assistant
{
    private readonly ContactCapture $capture;

    private readonly QuickAnswers $quick;

    private readonly RulesSearch $search;

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly Catalogue $catalogue,
        private readonly ConversationStore $store,
        private readonly LeadSink $leads,
        private readonly RulesPack $pack,
        private readonly array $config,
        private readonly ?LanguageModel $model = null,
        private readonly ?ToolRegistry $tools = null,
    ) {
        $this->capture = new ContactCapture($leads, $store, $config);
        $this->quick = new QuickAnswers($pack, $config);
        $this->search = new RulesSearch($catalogue, $pack, $config);
    }

    /**
     * Loads config/assistant.php and packs/{pack}.php from a directory.
     * The Laravel service provider and the demo both come through here.
     */
    public static function fromConfig(
        string $root,
        Catalogue $catalogue,
        ConversationStore $store,
        LeadSink $leads,
        ?LanguageModel $model = null,
        ?ToolRegistry $tools = null,
    ): self {
        $config = require rtrim($root, '/\\') . '/config/assistant.php';
        $pack = RulesPack::load(rtrim($root, '/\\') . '/packs/' . ($config['pack'] ?? 'property') . '.php');

        return new self($catalogue, $store, $leads, $pack, $config, $model, $tools);
    }

    public function reply(string $conversationId, string $message, ?string $itemSlug = null): Reply
    {
        $conversation = $this->store->load($conversationId, $itemSlug);

        // The item in view follows the visitor: a chat that starts on the home
        // page and then opens an item must pick it up on the next message.
        if ($itemSlug !== null) {
            $conversation->itemSlug = $itemSlug;
        }

        $conversation->recordVisitor($message);

        $reply = $this->respond($conversation, $message);

        $conversation->recordAssistant($reply->text, $reply->kind);
        $this->store->save($conversation);

        return $reply;
    }

    private function respond(Conversation $conversation, string $message): Reply
    {
        $item = $conversation->itemSlug !== null ? $this->catalogue->find($conversation->itemSlug) : null;
        $facts = $item !== null ? $this->catalogue->facts($item) : [];

        if ($reply = $this->capture->capture($conversation, $message)) {
            return $reply;
        }

        $limit = (int) ($this->config['limits']['max_messages'] ?? 30);

        if ($conversation->visitorMessages > $limit) {
            return new Reply($this->ask('handoff'), 'limit');
        }

        if ($reply = $this->quick->answer($conversation, $message, $facts)) {
            return $reply;
        }

        if ($reply = $this->search->answer($conversation, $message)) {
            return $reply;
        }

        if ($this->model !== null && $this->model->isAvailable()) {
            if ($reply = $this->viaModel($conversation, $item, $facts)) {
                return $reply;
            }
        }

        return new Reply($this->fallback($conversation), 'fallback');
    }

    /**
     * @param  array<string, mixed>|null  $item
     * @param  array<string, string>  $facts
     */
    private function viaModel(Conversation $conversation, ?array $item, array $facts): ?Reply
    {
        $tools = new ToolBox($this->catalogue, $this->leads, $this->store, $this->pack, $this->config, $this->tools);
        $prompt = (new PromptBuilder($this->pack, $this->config))->system($item, $facts);
        $history = (int) ($this->config['limits']['history'] ?? 12);

        try {
            $text = trim($this->model->converse(
                $prompt,
                $conversation->turns($history),
                $tools->definitions(),
                fn(string $name, array $input): mixed => $tools->run($name, $input, $conversation),
            ));
        } catch (\Throwable) {
            // Any provider trouble falls through to the fallback, which still
            // leaves the visitor a way to reach a person.
            return null;
        }

        if ($text === '') {
            return null;
        }

        // Asking for the details is the whole point of a deferral, so it is not
        // left to the model to remember.
        if ($tools->recordedUnanswered() && ! $conversation->isLead() && ! $this->asksForDetails($text)) {
            $text .= "\n\n" . $this->ask('details');
        }

        return new Reply($text, 'ai');
    }

    private function fallback(Conversation $conversation): string
    {
        $this->fileQuestion($conversation);

        $text = 'I do not have that answer to hand, so I will not guess.';

        return $text . ' ' . ($conversation->isLead()
            ? 'I have your details and ' . $this->from('agent') . ' will come back to you on it.'
            : $this->ask('details'));
    }

    private function fileQuestion(Conversation $conversation): void
    {
        $asked = $this->store->questions($conversation);
        $limit = (int) ($this->config['limits']['questions_per_chat'] ?? 5);

        if (count($asked) >= $limit) {
            return;
        }

        $question = null;

        foreach (array_reverse($conversation->messages) as $message) {
            if ($message['role'] === 'user' && trim((string) $message['content']) !== '') {
                $question = trim((string) $message['content']);
                break;
            }
        }

        if ($question === null || str_contains($question, '@')) {
            return;
        }

        foreach ($asked as $entry) {
            if (mb_strtolower((string) $entry['question']) === mb_strtolower($question)) {
                return;
            }
        }

        $this->store->recordQuestion($conversation, $question, $conversation->itemSlug);
    }

    private function asksForDetails(string $reply): bool
    {
        return preg_match('/\b(?:e-?mails?|contact you|call you|your (?:name|number|details))\b/i', $reply) === 1;
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
