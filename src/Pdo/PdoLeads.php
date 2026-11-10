<?php

namespace AssistantKit\Pdo;

use AssistantKit\Contracts\LeadSink;
use InvalidArgumentException;
use PDO;

/**
 * A captured lead becomes one row, over PDO, with no ORM. A site that already
 * has an enquiries table should write its own sink against that table; this is
 * the default so the leads port is never the reason a first launch is delayed.
 */
final class PdoLeads implements LeadSink
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table = 'ai_leads',
    ) {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $this->table) !== 1) {
            throw new InvalidArgumentException("Unsafe table name: {$this->table}");
        }
    }

    public function capture(array $lead): string
    {
        // Own the reference rather than the driver's auto-increment, so the same
        // insert works on every engine and the transcript can cite it immediately.
        $ref = 'lead-' . bin2hex(random_bytes(5));

        $statement = $this->pdo->prepare(
            "INSERT INTO {$this->table} (ref, name, email, phone, message, item, source, chat, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $statement->execute([
            $ref,
            $lead['name'] ?? null,
            $lead['email'] ?? null,
            $lead['phone'] ?? null,
            $lead['message'] ?? null,
            $lead['item'] ?? null,
            $lead['source'] ?? 'chat',
            $lead['chat'] ?? null,
            date('c'),
        ]);

        return $ref;
    }

    /**
     * @return list<string> the DDL it ran
     */
    public function migrate(): array
    {
        $text = match ((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'mysql' => 'VARCHAR(191)',
            default => 'TEXT',
        };

        $statements = [
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                ref {$text} PRIMARY KEY,
                name {$text} NULL,
                email {$text} NULL,
                phone {$text} NULL,
                message {$text} NULL,
                item {$text} NULL,
                source {$text} NULL,
                chat {$text} NULL,
                created_at {$text} NULL
            )",
        ];

        foreach ($statements as $sql) {
            $this->pdo->exec($sql);
        }

        return $statements;
    }

    /**
     * Read a stored lead back by reference. Not part of the port; the demo and
     * tests use it to prove the row actually landed.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $ref): ?array
    {
        $statement = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE ref = ?");
        $statement->execute([$ref]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
