<?php

namespace AssistantKit\Pdo;

use AssistantKit\Contracts\ConversationStore;
use AssistantKit\Value\Conversation;
use InvalidArgumentException;
use PDO;

/**
 * Chat persistence over PDO, so a site with no ORM still gets real memory.
 * One row per chat, one row per message, one row per filed question. The same
 * three tables build on sqlite, MySQL/MariaDB or Postgres (see migrate()).
 *
 * Nothing here knows about Laravel. On a framework you would rather bind your
 * own Eloquent store; this exists so the plain-PHP path is never the blocker.
 */
final class PdoStore implements ConversationStore
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $chats = 'ai_chats',
        private readonly string $messages = 'ai_chat_messages',
        private readonly string $questions = 'ai_unanswered_questions',
    ) {
        foreach ([$this->chats, $this->messages, $this->questions] as $table) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
                throw new InvalidArgumentException("Unsafe table name: {$table}");
            }
        }
    }

    public function load(string $id, ?string $itemSlug = null): Conversation
    {
        $statement = $this->pdo->prepare(
            "SELECT item_slug, visitor_messages, lead_ref FROM {$this->chats} WHERE id = ?"
        );
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $conversation = new Conversation(
            $id,
            ($row !== false && $row['item_slug'] !== null) ? (string) $row['item_slug'] : $itemSlug,
            (int) ($row['visitor_messages'] ?? 0),
        );
        $conversation->leadRef = ($row !== false && $row['lead_ref'] !== null) ? (string) $row['lead_ref'] : null;

        $statement = $this->pdo->prepare(
            "SELECT role, content, answered_by FROM {$this->messages} WHERE chat_id = ? ORDER BY idx"
        );
        $statement->execute([$id]);

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $message) {
            $conversation->messages[] = [
                'role' => (string) $message['role'],
                'content' => (string) $message['content'],
                'answered_by' => $message['answered_by'] !== null ? (string) $message['answered_by'] : null,
            ];
        }

        return $conversation;
    }

    public function save(Conversation $conversation): void
    {
        // Portable upsert: UPDATE, then INSERT only when nothing matched. Avoids
        // the ON CONFLICT / ON DUPLICATE dialects that differ per driver.
        $update = $this->pdo->prepare(
            "UPDATE {$this->chats} SET item_slug = ?, visitor_messages = ?, lead_ref = ?, updated_at = ? WHERE id = ?"
        );
        $update->execute([
            $conversation->itemSlug,
            $conversation->visitorMessages,
            $conversation->leadRef,
            date('c'),
            $conversation->id,
        ]);

        if ($update->rowCount() === 0) {
            $insert = $this->pdo->prepare(
                "INSERT INTO {$this->chats} (id, item_slug, visitor_messages, lead_ref, updated_at) VALUES (?, ?, ?, ?, ?)"
            );
            $insert->execute([
                $conversation->id,
                $conversation->itemSlug,
                $conversation->visitorMessages,
                $conversation->leadRef,
                date('c'),
            ]);
        }

        // Rewrite the transcript in order. Chats are short; keeping idx equal to
        // the array position means a reload never reorders the model's context.
        $delete = $this->pdo->prepare("DELETE FROM {$this->messages} WHERE chat_id = ?");
        $delete->execute([$conversation->id]);

        $insert = $this->pdo->prepare(
            "INSERT INTO {$this->messages} (chat_id, idx, role, content, answered_by) VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($conversation->messages as $index => $message) {
            $insert->execute([
                $conversation->id,
                $index,
                $message['role'],
                $message['content'],
                $message['answered_by'] ?? null,
            ]);
        }
    }

    public function recordQuestion(Conversation $conversation, string $question, ?string $itemSlug = null): void
    {
        $statement = $this->pdo->prepare(
            "SELECT COALESCE(MAX(seq), -1) + 1 FROM {$this->questions} WHERE chat_id = ?"
        );
        $statement->execute([$conversation->id]);
        $seq = (int) $statement->fetchColumn();

        $insert = $this->pdo->prepare(
            "INSERT INTO {$this->questions} (chat_id, seq, question, item_slug) VALUES (?, ?, ?, ?)"
        );
        $insert->execute([$conversation->id, $seq, $question, $itemSlug]);
    }

    public function questions(Conversation $conversation): array
    {
        $statement = $this->pdo->prepare(
            "SELECT question, item_slug FROM {$this->questions} WHERE chat_id = ? ORDER BY seq"
        );
        $statement->execute([$conversation->id]);

        $filed = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $filed[] = [
                'question' => (string) $row['question'],
                'item' => $row['item_slug'] !== null ? (string) $row['item_slug'] : null,
            ];
        }

        return $filed;
    }

    /**
     * Build the three tables on the current connection, picking column types for
     * the driver in use. Idempotent. Returns the DDL it ran, so a caller can log
     * or copy it into a migration.
     *
     * @return list<string>
     */
    public function migrate(): array
    {
        $text = match ($this->driver()) {
            'mysql' => 'VARCHAR(191)',
            default => 'TEXT',
        };
        $int = match ($this->driver()) {
            'mysql' => 'INT',
            default => 'INTEGER',
        };

        $statements = [
            "CREATE TABLE IF NOT EXISTS {$this->chats} (
                id {$text} PRIMARY KEY,
                item_slug {$text} NULL,
                visitor_messages {$int} NOT NULL DEFAULT 0,
                lead_ref {$text} NULL,
                updated_at {$text} NULL
            )",
            "CREATE TABLE IF NOT EXISTS {$this->messages} (
                chat_id {$text} NOT NULL,
                idx {$int} NOT NULL,
                role {$text} NOT NULL,
                content {$text} NOT NULL,
                answered_by {$text} NULL,
                PRIMARY KEY (chat_id, idx)
            )",
            "CREATE TABLE IF NOT EXISTS {$this->questions} (
                chat_id {$text} NOT NULL,
                seq {$int} NOT NULL,
                question {$text} NOT NULL,
                item_slug {$text} NULL,
                PRIMARY KEY (chat_id, seq)
            )",
        ];

        foreach ($statements as $sql) {
            $this->pdo->exec($sql);
        }

        return $statements;
    }

    private function driver(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
