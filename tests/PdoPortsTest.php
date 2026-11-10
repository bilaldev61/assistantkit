<?php

namespace AssistantKit\Tests;

use AssistantKit\Assistant;
use AssistantKit\Contracts\Catalogue;
use AssistantKit\Demo\FakeCatalogue;
use AssistantKit\Pdo\PdoLeads;
use AssistantKit\Pdo\PdoStore;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * The two ports the Kit ships ready-made, exercised over a real PDO connection.
 * In-memory sqlite stands in for MySQL so migrate() and the queries are proven,
 * not assumed — including the upsert and ordering paths a fake store never hits.
 */
final class PdoPortsTest extends TestCase
{
    private function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        (new PdoStore($pdo))->migrate();

        return $pdo;
    }

    public function test_a_conversation_survives_a_reload(): void
    {
        $pdo = $this->pdo();
        $store = new PdoStore($pdo);

        $conversation = $store->load('chat-1', 'marina-2br');
        $conversation->recordVisitor('how much is it?');
        $conversation->recordAssistant('The asking price is AED 2,100,000.', 'rule:fact_price');
        $conversation->visitorMessages = 1;
        $store->save($conversation);

        // A brand new store on the same connection is a fresh request.
        $reloaded = (new PdoStore($pdo))->load('chat-1');

        $this->assertSame('marina-2br', $reloaded->itemSlug);
        $this->assertSame(1, $reloaded->visitorMessages);
        $this->assertCount(2, $reloaded->messages);
        $this->assertSame('user', $reloaded->messages[0]['role']);
        $this->assertSame('rule:fact_price', $reloaded->messages[1]['answered_by']);
    }

    public function test_a_lead_reference_set_on_the_chat_is_persisted(): void
    {
        $pdo = $this->pdo();
        $store = new PdoStore($pdo);

        $conversation = $store->load('chat-2');
        $conversation->leadRef = 'lead-48';
        $store->save($conversation);

        $this->assertTrue((new PdoStore($pdo))->load('chat-2')->isLead());
        $this->assertSame('lead-48', (new PdoStore($pdo))->load('chat-2')->leadRef);
    }

    public function test_saving_the_same_chat_twice_updates_rather_than_duplicates(): void
    {
        $pdo = $this->pdo();
        $store = new PdoStore($pdo);

        $conversation = $store->load('chat-3');
        $conversation->recordVisitor('first');
        $store->save($conversation);

        $conversation->recordAssistant('reply', 'fallback');
        $conversation->visitorMessages = 1;
        $store->save($conversation); // the UPDATE branch

        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM ai_chats')->fetchColumn());
        $reloaded = (new PdoStore($pdo))->load('chat-3');
        $this->assertCount(2, $reloaded->messages);
    }

    public function test_filed_questions_come_back_in_the_order_they_were_asked(): void
    {
        $pdo = $this->pdo();
        $store = new PdoStore($pdo);
        $conversation = $store->load('chat-4');

        $store->recordQuestion($conversation, 'do you have a gym?', 'marina-2br');
        $store->recordQuestion($conversation, 'is there a school nearby?');

        $filed = (new PdoStore($pdo))->questions($conversation);

        $this->assertCount(2, $filed);
        $this->assertSame('do you have a gym?', $filed[0]['question']);
        $this->assertSame('marina-2br', $filed[0]['item']);
        $this->assertNull($filed[1]['item']);
    }

    public function test_a_captured_lead_can_be_read_back(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $leads = new PdoLeads($pdo);
        $leads->migrate();

        $ref = $leads->capture([
            'name' => 'Ahmed',
            'email' => 'ahmed@example.com',
            'message' => 'call me',
            'chat' => 'chat-9',
        ]);

        $row = $leads->find($ref);

        $this->assertIsArray($row);
        $this->assertSame('ahmed@example.com', $row['email']);
        $this->assertSame('chat', $row['source']); // the sink fills the default
        $this->assertStringStartsWith('lead-', $ref);
    }

    public function test_the_engine_answers_a_real_question_through_the_pdo_store(): void
    {
        $kitRoot = dirname(__DIR__);
        $pdo = $this->pdo();
        (new PdoLeads($pdo))->migrate();

        $catalogue = new class implements Catalogue {
            public function search(array $criteria, int $limit): array
            {
                return (new FakeCatalogue())->search($criteria, $limit);
            }

            public function find(string $slug): ?array
            {
                return (new FakeCatalogue())->find($slug);
            }

            public function facts(array $item): array
            {
                return (new FakeCatalogue())->facts($item);
            }
        };

        $assistant = Assistant::fromConfig($kitRoot, $catalogue, new PdoStore($pdo), new PdoLeads($pdo));
        $reply = $assistant->reply('chat-live', 'what is the price?', 'marina-2br');

        $this->assertTrue($reply->isRule(), 'expected a rule answer, got ' . $reply->kind);
        $this->assertStringContainsString('2,100,000', $reply->text);

        // The turn was written to the PDO chat, so the next request remembers it.
        $messages = (new PdoStore($pdo))->load('chat-live')->messages;
        $this->assertCount(2, $messages);
    }
}
