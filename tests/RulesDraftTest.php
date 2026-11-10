<?php

namespace AssistantKit\Tests;

use PHPUnit\Framework\TestCase;
use RulesCompiler;
use RulesDrafter;
use RulesInspector;

require_once dirname(__DIR__) . '/tools/RulesInspector.php';
require_once dirname(__DIR__) . '/tools/RulesCompiler.php';
require_once dirname(__DIR__) . '/tools/RulesDrafter.php';

/**
 * A drafted pack is only worth shipping if it builds, lints and round-trips.
 * These tests prove both drafters feed the existing rules-build → lint pipeline
 * without a model, a database or a hand-written regex.
 */
final class RulesDraftTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__);
    }

    private function configPath(): string
    {
        return $this->root() . '/config/assistant.php';
    }

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->tempFiles = [];
    }

    /** @return list<string> */
    private function errors(string $packPath): array
    {
        $inspector = RulesInspector::fromPaths($packPath, $this->configPath());
        $messages = [];

        foreach ($inspector->lint() as $finding) {
            if ($finding['level'] === 'error') {
                $messages[] = $finding['message'];
            }
        }

        return $messages;
    }

    /** @return array<string, mixed> */
    private function inventorySpec(): array
    {
        $json = json_decode((string) file_get_contents($this->root() . '/rules/inventory.example.json'), true);

        $this->assertIsArray($json, 'the shipped inventory example must be valid JSON');

        return $json;
    }

    public function test_a_drafted_inventory_builds_a_pack_that_lints_clean(): void
    {
        $table = RulesDrafter::fromInventory($this->inventorySpec());
        $code = RulesCompiler::compile($table, 'rules/furniture.rules.php');

        $packPath = tempnam(sys_get_temp_dir(), 'akbuilt') . '.php';
        $this->tempFiles[] = $packPath;
        file_put_contents($packPath, $code);

        $this->assertSame([], $this->errors($packPath), 'a drafted pack must build with no lint errors');
    }

    public function test_only_the_columns_marked_answerable_become_facts(): void
    {
        $table = RulesDrafter::fromInventory($this->inventorySpec());
        $byKey = [];

        foreach ($table['facts'] as $row) {
            $byKey[$row['key']] = $row;
        }

        $this->assertArrayHasKey('fact', $byKey['price'], 'price was marked answerable');
        $this->assertArrayHasKey('fact', $byKey['dimensions']);
        $this->assertArrayNotHasKey('fact', $byKey['delivery'], 'delivery has no fact column, so it must only defer');
        $this->assertNotEmpty($byKey['delivery']['defer']);
    }

    public function test_a_drafted_table_defaults_to_a_working_vocabulary(): void
    {
        $table = RulesDrafter::fromInventory($this->inventorySpec());

        $this->assertSame('furniture', $table['name']);
        $this->assertArrayHasKey('greeting', $table['intents'], 'a drafted pack must still greet');
        $this->assertArrayHasKey('hours', $table['agency']);
        $this->assertArrayHasKey('max', $table['criteria']['price']);
    }

    public function test_the_rendered_table_is_valid_php_and_survives_the_round_trip(): void
    {
        $table = RulesDrafter::fromInventory($this->inventorySpec());
        $path = tempnam(sys_get_temp_dir(), 'akdraft') . '.php';
        $this->tempFiles[] = $path;
        file_put_contents($path, RulesDrafter::render($table));

        $loaded = require $path;

        $this->assertSame($table, $loaded, 'the drafted table must read back exactly as it was built');
    }

    public function test_related_questions_cluster_into_one_topic(): void
    {
        $clusters = RulesDrafter::cluster([
            'do you deliver to sharjah',
            'can you deliver this sofa next week',
            'is assembly included',
        ]);

        $deliver = null;

        foreach ($clusters as $cluster) {
            if (in_array('deliver', $cluster['phrases'], true)) {
                $deliver = $cluster;
            }
        }

        $this->assertNotNull($deliver, 'both delivery questions must land in one topic');
        $this->assertSame(2, $deliver['count']);
    }

    public function test_topics_the_current_pack_answers_are_reported_not_redrafted(): void
    {
        $existing = RulesInspector::fromPaths($this->root() . '/packs/property.php', $this->configPath());

        $draft = RulesDrafter::fromQuestions([
            'is the price negotiable',
            'do you have a parking space',
            'what would you accept',
        ], $existing, 'property');

        $coveredKeys = array_column($draft['covered'], 'rule');
        $this->assertContains('negotiable', $coveredKeys);
        $this->assertContains('parking', $coveredKeys);

        $newKeys = array_column((array) $draft['table']['facts'], 'key');
        $this->assertNotContains('negotiable', $newKeys, 'a covered topic must not be drafted again');
        $this->assertContains('accept', $newKeys, 'the unanswerable topic must be drafted');
    }

    public function test_every_drafted_question_row_defers_until_a_fact_is_wired(): void
    {
        $draft = RulesDrafter::fromQuestions(['can i pay in instalments', 'do you make custom sizes']);

        foreach ($draft['table']['facts'] as $row) {
            $this->assertArrayNotHasKey('fact', $row, 'a logged question proves wording, not data');
            $this->assertNotEmpty($row['defer']);
        }
    }

    public function test_the_cli_drafts_a_table_from_the_shipped_example(): void
    {
        if (! function_exists('shell_exec')) {
            $this->markTestSkipped('shell_exec is disabled');
        }

        $php = escapeshellarg(PHP_BINARY);
        $script = escapeshellarg($this->root() . '/bin/rules-draft.php');
        $spec = escapeshellarg($this->root() . '/rules/inventory.example.json');

        chdir($this->root());
        $out = (string) shell_exec("$php $script inventory $spec 2>&1");

        $this->assertStringContainsString('return [', $out);
        $this->assertStringContainsString("'furniture'", $out);
        $this->assertStringContainsString('=> \'delivery\'', $out);
    }
}
