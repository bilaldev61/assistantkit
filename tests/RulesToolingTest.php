<?php

namespace AssistantKit\Tests;

use PHPUnit\Framework\TestCase;
use RulesCompiler;
use RulesInspector;
use CaseRunner;
use RuntimeException;

require_once dirname(__DIR__) . '/tools/RulesInspector.php';
require_once dirname(__DIR__) . '/tools/CaseRunner.php';
require_once dirname(__DIR__) . '/tools/RulesCompiler.php';

/**
 * The tooling is only useful if it keeps working: these tests lint the shipped
 * pack, replay every case, and rebuild the generated pack from its table.
 */
final class RulesToolingTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__);
    }

    private function inspector(string $pack = 'property'): RulesInspector
    {
        return RulesInspector::fromPaths(
            $this->root() . '/packs/' . $pack . '.php',
            $this->root() . '/config/assistant.php',
        );
    }

    /** @return list<string> */
    private function errors(RulesInspector $inspector): array
    {
        $messages = [];

        foreach ($inspector->lint() as $finding) {
            if ($finding['level'] === 'error') {
                $messages[] = $finding['message'];
            }
        }

        return $messages;
    }

    public function test_the_property_pack_lints_without_errors(): void
    {
        $this->assertSame([], $this->errors($this->inspector()));
    }

    public function test_every_property_case_still_answers_the_way_it_did(): void
    {
        $table = require $this->root() . '/rules/property.cases.php';
        $results = (new CaseRunner($this->inspector()))->run($table['cases']);

        $failed = [];

        foreach ($results as $result) {
            if (! $result['ok']) {
                $failed[] = $result['ask'] . ' → got ' . $result['got']
                    . ', expected ' . $result['expect'] . "\n" . $result['text'];
            }
        }

        $this->assertSame([], $failed, "Rule cases failing:\n" . implode("\n\n", $failed));
    }

    public function test_the_bikes_pack_matches_its_table(): void
    {
        $table = require $this->root() . '/rules/bikes.rules.php';
        $generated = RulesCompiler::compile($table, 'rules/bikes.rules.php');
        $committed = str_replace("\r\n", "\n", (string) file_get_contents($this->root() . '/packs/bikes.php'));

        $this->assertSame(
            $generated,
            $committed,
            'packs/bikes.php is stale: run php bin/rules-build.php rules/bikes.rules.php --out=packs/bikes.php',
        );
    }

    public function test_the_generated_bikes_pack_is_loadable_and_lints(): void
    {
        $pack = require $this->root() . '/packs/bikes.php';

        $this->assertSame('bikes', $pack['name']);
        $this->assertStringContainsString('road bike', (string) $pack['criteria']['type']);
        $this->assertSame([], $this->errors($this->inspector('bikes')));
    }

    public function test_the_compiler_refuses_a_broad_rule_above_a_narrow_one(): void
    {
        $table = [
            'name'  => 'trap',
            'facts' => [
                ['key' => 'price', 'phrases' => ['price', 'how much'], 'fact' => 'price'],
                ['key' => 'negotiable', 'phrases' => ['best price'], 'defer' => 'room on the price'],
            ],
        ];

        $problems = RulesCompiler::ambiguities($table);

        $this->assertCount(1, $problems);
        $this->assertStringContainsString('best price', $problems[0]);

        $this->expectException(RuntimeException::class);
        RulesCompiler::compile($table);
    }

    public function test_the_same_table_builds_once_the_order_is_fixed(): void
    {
        $table = [
            'name'  => 'fixed',
            'facts' => [
                ['key' => 'negotiable', 'phrases' => ['best price'], 'defer' => 'room on the price'],
                ['key' => 'price', 'phrases' => ['price', 'how much'], 'fact' => 'price'],
            ],
        ];

        $code = RulesCompiler::compile($table);

        $this->assertStringContainsString("'negotiable' => [", $code);
        $this->assertStringNotContainsString('--force', $code);
    }

    public function test_the_inspector_names_the_rule_that_fires(): void
    {
        $rule = $this->inspector()->firstFactRule('is the price negotiable?');

        $this->assertNotNull($rule);
        $this->assertSame('negotiable', $rule['key']);
        $this->assertNull($rule['fact']);
        $this->assertSame('whether there is room on the price', $rule['defer']);
    }
}
