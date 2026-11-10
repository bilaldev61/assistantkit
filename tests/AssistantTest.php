<?php

namespace AssistantKit\Tests;

use AssistantKit\Assistant;
use AssistantKit\Contracts\LanguageModel;
use AssistantKit\Demo\FakeCatalogue;
use AssistantKit\Demo\MemoryLeadSink;
use AssistantKit\Demo\MemoryStore;
use AssistantKit\Demo\ScriptedModel;
use AssistantKit\Pack\RulesPack;
use AssistantKit\Value\Conversation;
use PHPUnit\Framework\TestCase;

class AssistantTest extends TestCase
{
    private FakeCatalogue $catalogue;

    private MemoryStore $store;

    private MemoryLeadSink $leads;

    protected function setUp(): void
    {
        $this->catalogue = new FakeCatalogue();
        $this->store = new MemoryStore();
        $this->leads = new MemoryLeadSink();
    }

    public function test_an_email_in_the_first_message_turns_the_chat_into_a_lead(): void
    {
        $reply = $this->assistant()->reply('chat-1', 'I am Sara, sara@example.com - is the marina flat negotiable?');

        $this->assertSame('lead', $reply->kind);
        $this->assertCount(1, $this->leads->leads);
        $this->assertSame('sara@example.com', $this->leads->leads[0]['email']);
        $this->assertSame('Sara', $this->leads->leads[0]['name']);
        $this->assertStringContainsString('sara@example.com', $reply->text);
    }

    public function test_a_lead_remembers_the_item_the_visitor_was_reading(): void
    {
        $this->assistant()->reply('chat-1', 'sara@example.com', 'marina-2br');

        $this->assertSame('marina-2br', $this->leads->leads[0]['item']);
    }

    public function test_a_second_email_does_not_create_a_second_lead(): void
    {
        $assistant = $this->assistant();

        $assistant->reply('chat-1', 'sara@example.com');
        $assistant->reply('chat-1', 'thanks, also omar@example.com');

        $this->assertCount(1, $this->leads->leads);
    }

    public function test_a_recorded_fact_answers_before_any_model_is_called(): void
    {
        $model = new ScriptedModel('From the model.');
        $reply = $this->assistant($model)->reply('chat-1', 'how much is it?', 'marina-2br');

        $this->assertSame('rule:fact_price', $reply->kind);
        $this->assertStringContainsString('AED 2,100,000', $reply->text);
        $this->assertSame([], $model->prompts);
    }

    public function test_a_missing_fact_is_deferred_and_asks_for_an_email(): void
    {
        $reply = $this->assistant()->reply('chat-1', 'is it negotiable?', 'marina-2br');

        $this->assertSame('rule:deferral_negotiable', $reply->kind);
        $this->assertStringContainsString('will not', $reply->text);
        $this->assertStringContainsString('name and email', $reply->text);
    }

    public function test_the_email_ask_disappears_once_the_chat_is_a_lead(): void
    {
        $assistant = $this->assistant();

        $assistant->reply('chat-1', 'sara@example.com', 'marina-2br');
        $reply = $assistant->reply('chat-1', 'is it negotiable?', 'marina-2br');

        $this->assertStringContainsString('I have your details', $reply->text);
        $this->assertStringNotContainsString('Leave your name', $reply->text);
    }

    public function test_contact_details_come_from_config(): void
    {
        $reply = $this->assistant()->reply('chat-1', 'what is your phone number');

        $this->assertSame('rule:agency_contact', $reply->kind);
        $this->assertStringContainsString('+971 00 000 0000', $reply->text);
    }

    public function test_a_search_returns_the_matching_items(): void
    {
        $reply = $this->assistant()->reply('chat-1', 'do you have a 2 bedroom apartment in dubai marina for sale');

        $this->assertSame('rule:search', $reply->kind);
        $this->assertStringContainsString('2-bedroom apartment in Dubai Marina', $reply->text);
        $this->assertStringNotContainsString('villa', $reply->text);
    }

    public function test_an_alias_is_understood_as_the_area_name(): void
    {
        $reply = $this->assistant()->reply('chat-1', 'any studio for rent in jvc');

        $this->assertSame('rule:search', $reply->kind);
        $this->assertStringContainsString('Studio in Jumeirah Village Circle', $reply->text);
    }

    public function test_a_search_with_no_matches_asks_for_details(): void
    {
        $reply = $this->assistant()->reply('chat-1', 'villa in difc for sale');

        $this->assertSame('rule:search_none', $reply->kind);
        $this->assertStringContainsString('nothing', $reply->text);
        $this->assertStringContainsString('name and email', $reply->text);
    }

    public function test_process_questions_are_answered_from_config_without_figures(): void
    {
        $reply = $this->assistant()->reply('chat-1', 'how does renting work');

        $this->assertSame('rule:process', $reply->kind);
        $this->assertStringContainsString('How renting works with us', $reply->text);
        $this->assertStringNotContainsString('AED', $reply->text);
    }

    public function test_the_model_is_only_reached_when_the_rules_cannot_answer(): void
    {
        $model = new ScriptedModel('Downtown is quieter, Marina is busier.');
        $reply = $this->assistant($model)->reply('chat-1', 'which area is better for families');

        $this->assertSame('ai', $reply->kind);
        $this->assertStringContainsString('Downtown is quieter', $reply->text);
        $this->assertCount(1, $model->prompts);
        $this->assertStringContainsString('Example Properties', $model->prompts[0]);
    }

    public function test_a_model_deferral_without_an_ask_gets_one_appended(): void
    {
        $reply = $this->assistant(new ScriptedModel('defer'))->reply('chat-1', 'what nationality is the owner');

        $this->assertSame('ai', $reply->kind);
        $this->assertStringContainsString('I cannot answer that one', $reply->text);
        $this->assertStringContainsString('name and email', $reply->text);
        $this->assertCount(1, $this->store->questions($this->store->load('chat-1')));
    }

    public function test_the_message_cap_hands_the_chat_over_with_a_way_to_reply(): void
    {
        $assistant = $this->assistant(null, ['limits' => ['max_messages' => 1]]);

        $assistant->reply('chat-1', 'hello');
        $reply = $assistant->reply('chat-1', 'and another thing');

        $this->assertSame('limit', $reply->kind);
        $this->assertStringContainsString('name and email', $reply->text);
    }

    public function test_the_fallback_files_the_question_and_never_ends_empty_handed(): void
    {
        $reply = $this->assistant()->reply('chat-1', 'what nationality is the owner');

        $this->assertSame('fallback', $reply->kind);
        $this->assertStringContainsString('name and email', $reply->text);

        $questions = $this->store->questions($this->store->load('chat-1'));

        $this->assertCount(1, $questions);
        $this->assertSame('what nationality is the owner', $questions[0]['question']);
    }

    public function test_the_transcript_keeps_every_turn_with_how_it_was_answered(): void
    {
        $assistant = $this->assistant();
        $assistant->reply('chat-1', 'hello');
        $assistant->reply('chat-1', 'how does renting work');

        $conversation = $this->store->load('chat-1');

        $this->assertInstanceOf(Conversation::class, $conversation);
        $this->assertCount(4, $conversation->messages);
        $this->assertSame(['user', 'assistant', 'user', 'assistant'], array_column($conversation->messages, 'role'));

        $kinds = array_values(array_filter(array_column($conversation->messages, 'answered_by')));

        $this->assertSame(['rule:greeting', 'rule:process'], $kinds);
    }

    public function test_a_plural_type_word_still_filters_by_that_type(): void
    {
        $reply = $this->assistant()->reply('chat-1', 'apartments in dubai marina for sale');

        // Visitors type the plural. A type word that never matches does not
        // narrow to nothing, it drops the filter, and the villa arrives at
        // someone who asked for apartments.
        $this->assertSame('rule:search', $reply->kind);
        $this->assertStringContainsString('2-bedroom apartment in Dubai Marina', $reply->text);
        $this->assertStringNotContainsString('4-bedroom villa', $reply->text);
    }

    public function test_a_misspelt_hours_question_is_still_answered_from_config(): void
    {
        $model = new ScriptedModel('From the model.');

        $reply = $this->assistant($model)->reply('chat-1', 'office hourss');

        // The model has no way to know our timings, so a typo here costs a call
        // and still returns a deferral.
        $this->assertSame('rule:agency_hours', $reply->kind);
        $this->assertStringContainsString('Saturday to Thursday', $reply->text);
        $this->assertSame([], $model->prompts);
    }

    public function test_hours_used_as_a_measurement_is_not_answered_with_opening_times(): void
    {
        $reply = $this->assistant()->reply('chat-1', 'is there a 24 hours gym in the building');

        $this->assertNotSame('rule:agency_hours', $reply->kind);
        $this->assertStringNotContainsString('Saturday to Thursday', $reply->text);
    }

    public function test_a_judgement_that_names_filters_still_reaches_the_model(): void
    {
        $model = new ScriptedModel('The Marina one lives better with children.');

        $reply = $this->assistant($model)->reply('chat-1', 'compare a 2 bedroom in marina with a 3 bedroom in downtown');

        // The filters are real, but the visitor asked for an opinion: listing
        // what matches answers a question nobody asked.
        $this->assertSame('ai', $reply->kind);
        $this->assertCount(1, $model->prompts);
    }

    public function test_a_lone_type_word_is_not_answered_as_a_search(): void
    {
        $model = new ScriptedModel('Four lines, none of them a listing.');

        $reply = $this->assistant($model)->reply('chat-1', 'write me a poem about an apartment');

        $this->assertSame('ai', $reply->kind);
        $this->assertCount(1, $model->prompts);
    }

    public function test_a_judgement_is_shortlisted_when_no_model_is_available(): void
    {
        // With nothing behind the layer the strictness is off: an opinion is
        // impossible either way, but the matching listings are still true.
        $reply = $this->assistant()->reply('chat-1', 'which 2 bedroom in marina is worth buying');

        $this->assertSame('rule:search', $reply->kind);
        $this->assertStringContainsString('2-bedroom apartment in Dubai Marina', $reply->text);
    }

    public function test_an_identity_question_is_answered_without_the_model(): void
    {
        $model = new ScriptedModel('Should not run.');

        $reply = $this->assistant($model)->reply('chat-1', 'are you a real person or ai');

        $this->assertSame('rule:identity', $reply->kind);
        $this->assertStringContainsString('website assistant', $reply->text);
        $this->assertSame([], $model->prompts);
    }

    public function test_greeting_with_filler_words_is_still_small_talk(): void
    {
        $model = new ScriptedModel('Should not run.');

        foreach (['hlo ge', 'hi ji', 'hello yaar', 'salam sahab'] as $msg) {
            $reply = $this->assistant($model)->reply("chat-{$msg}", $msg);
            $this->assertSame('rule:greeting', $reply->kind, "\"{$msg}\" should be greeting");
        }

        $this->assertSame([], $model->prompts);
    }

    public function test_a_process_question_about_viewings_is_answered_from_config(): void
    {
        $model = new ScriptedModel('Should not run.');

        $reply = $this->assistant($model)->reply('chat-1', 'how do i book a viewing');

        $this->assertSame('rule:process', $reply->kind);
        $this->assertStringContainsString('viewings', $reply->text);
        $this->assertSame([], $model->prompts);
    }

    /** @param array<string, mixed> $overrides */
    private function assistant(?LanguageModel $model = null, array $overrides = []): Assistant
    {
        $root = dirname(__DIR__);
        $config = array_replace_recursive(require $root . '/config/assistant.php', $overrides);

        return new Assistant(
            $this->catalogue,
            $this->store,
            $this->leads,
            RulesPack::load($root . '/packs/property.php'),
            $config,
            $model,
        );
    }
}
